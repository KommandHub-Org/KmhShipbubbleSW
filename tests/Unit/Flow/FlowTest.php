<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Flow;

use Kommandhub\ShipbubbleSW\Flow\CreateShipmentAction;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Kommandhub\ShipbubbleSW\Flow\FlowInstaller;
use Kommandhub\ShipbubbleSW\Shipment\Service\CreateResult;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\OrderAware;

#[CoversClass(CreateShipmentAction::class)]
#[CoversClass(FlowInstaller::class)]
#[UsesClass(CreateResult::class)]
#[UsesClass(ShipmentStatus::class)]
class FlowTest extends TestCase
{
    public function testActionIdentity(): void
    {
        static::assertSame('action.kmh.shipbubble.create_shipment', CreateShipmentAction::getName());
        $action = new CreateShipmentAction($this->createStub(EntityRepository::class), $this->createStub(ShipmentService::class), $this->createStub(LoggerInterface::class));
        static::assertSame([OrderAware::class], $action->requirements());
    }

    public function testBooksEveryQuotedDeliveryAndKeepsGoingOnFailure(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturnCallback(function (Criteria $criteria, Context $context): IdSearchResult {
            static::assertCount(2, $criteria->getFilters());

            return new IdSearchResult(3, [['primaryKey' => 's1', 'data' => []], ['primaryKey' => 's2', 'data' => []], ['primaryKey' => 's3', 'data' => []]], $criteria, $context);
        });
        $service = $this->createMock(ShipmentService::class);
        $service->expects(static::exactly(3))->method('create')->willReturnCallback(static fn (string $id): CreateResult => match ($id) {
            's1' => CreateResult::booked(),
            's2' => CreateResult::confirmationNeeded(1.0, 2.0, 'One', 'NGN'),
            default => throw new \RuntimeException('wallet empty'),
        });
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('notice');
        $logger->expects(static::once())->method('error');

        (new CreateShipmentAction($repository, $service, $logger))->handleFlow($this->flow('order-1'));
    }

    public function testWithoutOrderNothingHappens(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::never())->method('searchIds');

        (new CreateShipmentAction($repository, $this->createStub(ShipmentService::class), $this->createStub(LoggerInterface::class)))->handleFlow($this->flow(null));
    }

    public function testInstallsThreeActiveFlowsWithTheirMailTemplates(): void
    {
        $created = [];
        $flows = $this->createMock(EntityRepository::class);
        $flows->method('searchIds')->willReturn($this->ids([]));
        $flows->expects(static::exactly(3))->method('create')->willReturnCallback(function (array $payload) use (&$created): EntityWrittenContainerEvent {
            $created[] = $payload[0];

            return self::written();
        });
        $types = $this->createMock(EntityRepository::class);
        $types->method('searchIds')->willReturnOnConsecutiveCalls($this->ids([]), $this->ids(['existing-type']));
        $types->expects(static::once())->method('create')->willReturn(self::written());
        $templates = $this->createMock(EntityRepository::class);
        $templates->expects(static::exactly(2))->method('create')->willReturnCallback(static function (array $payload): EntityWrittenContainerEvent {
            static::assertSame('{{ salesChannel.translated.name }}', $payload[0]['translations']['en-GB']['senderName']);
            static::assertArrayHasKey('de-DE', $payload[0]['translations']);

            return self::written();
        });

        (new FlowInstaller($flows, $types, $templates))->install(Context::createDefaultContext());

        static::assertSame(FlowInstaller::flowIds(), array_column($created, 'id'));
        static::assertSame([true, true, true], array_column($created, 'active'));
        static::assertSame([FlowInstaller::PAID_EVENT, ShipmentCreatedEvent::EVENT_NAME, ShipmentFailedEvent::EVENT_NAME], array_column($created, 'eventName'));
        static::assertSame(CreateShipmentAction::ACTION_NAME, $created[0]['sequences'][0]['actionName']);
        static::assertSame('default', $created[1]['sequences'][0]['config']['recipient']['type']);
        static::assertSame('admin', $created[2]['sequences'][0]['config']['recipient']['type']);
    }

    public function testExistingOrLegacyFlowsAreLeftAlone(): void
    {
        $flows = $this->createMock(EntityRepository::class);
        // paid flow missing by id but its action is used by an older flow; the other two exist
        $flows->method('searchIds')->willReturnOnConsecutiveCalls($this->ids([]), $this->ids(['legacy']), $this->ids(['f2']), $this->ids(['f3']));
        $flows->expects(static::never())->method('create');
        $mail = $this->createMock(EntityRepository::class);
        $mail->expects(static::never())->method('create');

        (new FlowInstaller($flows, $mail, $mail))->install(Context::createDefaultContext());
    }

    private static function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    private function flow(?string $orderId): StorableFlow
    {
        $flow = new StorableFlow('state_enter.order_transaction.state.paid', Context::createDefaultContext());

        if ($orderId !== null) {
            $flow->setData(OrderAware::ORDER_ID, $orderId);
        }

        return $flow;
    }

    /**
     * @param list<string> $ids
     */
    private function ids(array $ids): IdSearchResult
    {
        return new IdSearchResult(\count($ids), array_map(static fn (string $id): array => ['primaryKey' => $id, 'data' => []], $ids), new Criteria(), Context::createDefaultContext());
    }
}
