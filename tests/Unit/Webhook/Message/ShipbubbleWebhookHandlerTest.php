<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Webhook\Message;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Kommandhub\ShipbubbleSW\Webhook\Message\ShipbubbleWebhookHandler;
use Kommandhub\ShipbubbleSW\Webhook\Message\ShipbubbleWebhookMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

#[CoversClass(ShipbubbleWebhookHandler::class)]
#[CoversClass(ShipbubbleWebhookMessage::class)]
#[UsesClass(Shipment::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipmentStatus::class)]
class ShipbubbleWebhookHandlerTest extends TestCase
{
    private const EVENT_ID = '0190aaaa0000700080000000000000e1';

    private ?string $note = 'unset';

    public function testTheNewStatusComesFromPackageStatusNotTheBodysStatus(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->expects(static::once())->method('applyWebhook')
            ->willReturnCallback(static function (string $orderId, Shipment $label, ?ShipmentStatus $status): bool {
                static::assertSame('SB-1', $orderId);
                static::assertSame(ShipmentStatus::PickedUp, $status);
                static::assertSame('confirmed', $label->status);

                return true;
            });

        $this->handle($service, 'shipment.status.changed', [
            'order_id' => 'SB-1',
            'status' => 'confirmed',
            'package_status' => [['status' => 'Confirmed'], ['status' => 'Picked up']],
        ]);

        static::assertNull($this->note);
    }

    public function testCancelledEventAndMissingHistory(): void
    {
        $statuses = [];
        $service = $this->createMock(ShipmentService::class);
        $service->method('applyWebhook')->willReturnCallback(static function (string $orderId, Shipment $label, ?ShipmentStatus $status) use (&$statuses): bool {
            $statuses[] = $status;

            return true;
        });

        $this->handle($service, 'shipment.cancelled', ['order_id' => 'SB-1', 'status' => 'returned']);
        $this->handle($service, 'shipment.label.created', ['order_id' => 'SB-1']);

        static::assertSame([ShipmentStatus::Cancelled, null], $statuses);
    }

    public function testNotesWhatCouldNotBeApplied(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->method('applyWebhook')->willReturn(false);

        $this->handle($service, 'shipment.cod.remitted', ['order_id' => 'SB-1']);
        static::assertSame('Event not used by the plugin; stored only.', $this->note);

        $this->handle($service, 'shipment.status.changed', ['status' => 'pending']);
        static::assertSame('Webhook without a Shipbubble order id.', $this->note);

        $this->handle($service, 'shipment.status.changed', ['order_id' => 'SB-404']);
        static::assertSame('No shipment with this Shipbubble order id.', $this->note);
    }

    public function testAFailureIsLoggedAndNoted(): void
    {
        $service = $this->createMock(ShipmentService::class);
        $service->method('applyWebhook')->willThrowException(new \RuntimeException('database gone'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $this->handle($service, 'shipment.status.changed', ['order_id' => 'SB-1'], $logger);

        static::assertSame('Failed: database gone', $this->note);
    }

    public function testAlreadyProcessedEventsAreSkipped(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(false);
        $connection->expects(static::never())->method('executeStatement');
        $service = $this->createMock(ShipmentService::class);
        $service->expects(static::never())->method('applyWebhook');

        (new ShipbubbleWebhookHandler($connection, $service, new NullLogger()))(new ShipbubbleWebhookMessage(self::EVENT_ID));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handle(ShipmentService $service, string $event, array $payload, ?LoggerInterface $logger = null): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn(['event' => $event, 'payload' => json_encode($payload)]);
        $connection->expects(static::once())->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            static::assertStringContainsString('processed_at', $sql);
            $this->note = $params['note'];

            return 1;
        });

        (new ShipbubbleWebhookHandler($connection, $service, $logger ?? new NullLogger()))(new ShipbubbleWebhookMessage(self::EVENT_ID));
    }
}
