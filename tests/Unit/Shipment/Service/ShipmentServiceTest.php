<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipment\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\Resource\Labels;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentCollection;
use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentEntity;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\QuoteService;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipment\Service\CreateResult;
use Kommandhub\ShipbubbleSW\Shipment\Service\DeliveryStateUpdater;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentException;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ShipmentService::class)]
#[CoversClass(CreateResult::class)]
#[CoversClass(ShipmentException::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Labels::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(Shipment::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
#[UsesClass(ShipmentStatus::class)]
#[UsesClass(QuoteStruct::class)]
#[UsesClass(CourierOption::class)]
#[UsesClass(QuoteError::class)]
#[UsesClass(ShipmentEvent::class)]
#[UsesClass(ShipmentCreatedEvent::class)]
#[UsesClass(ShipmentFailedEvent::class)]
class ShipmentServiceTest extends TestCase
{
    private const SHIPMENT_ID = '0190aaaa0000700080000000000000a1';

    private const DELIVERY_ID = '0190aaaa0000700080000000000000d1';

    private const LABEL = ['order_id' => 'SB-1', 'status' => 'pending', 'courier' => ['name' => 'One'], 'tracking_url' => 'https://trackshipment.shipbubble.com/shipment/SB-1'];

    /**
     * @var list<array<string, mixed>>
     */
    private array $shipmentUpdates = [];

    /**
     * @var list<array<string, mixed>>
     */
    private array $deliveryUpdates = [];

    private QuoteService&MockObject $quoteService;

    private DeliveryStateUpdater&MockObject $stateUpdater;

    /**
     * @var list<object>
     */
    private array $events = [];

    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->quoteService = $this->createMock(QuoteService::class);
        $this->stateUpdater = $this->createMock(DeliveryStateUpdater::class);
        $events = &$this->events;
        $this->dispatcher = new class($events) implements EventDispatcherInterface {
            /**
             * @param list<object> $events
             */
            public function __construct(private array &$events)
            {
            }

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };
    }

    public function testBooksWithTheStoredTokenAndWritesTheTrackingCode(): void
    {
        $label = ClientFactory::ok(self::LABEL);

        $result = $this->service($this->shipment(), [$label], ['OLD'])->create(self::SHIPMENT_ID, false, Context::createDefaultContext());

        static::assertTrue($result->booked);
        static::assertSame(['booked' => true, 'oldCarrierPrice' => null, 'newCarrierPrice' => null, 'courierName' => null, 'currency' => null], $result->toArray());
        static::assertSame(['request_token' => 'tok', 'service_code' => 's1', 'courier_id' => 'c1'], json_decode($label->getRequestOptions()['body'], true));
        static::assertSame('SB-1', $this->shipmentUpdates[0]['shipbubbleOrderId']);
        static::assertSame('pending', $this->shipmentUpdates[0]['status']);
        static::assertSame([['id' => self::DELIVERY_ID, 'trackingCodes' => ['OLD', 'SB-1']]], $this->deliveryUpdates);
        static::assertCount(1, $this->events);
        static::assertInstanceOf(ShipmentCreatedEvent::class, $this->events[0]);
    }

    public function testAnExistingTrackingCodeOrMissingDeliveryIsNotWrittenAgain(): void
    {
        $this->service($this->shipment(), [ClientFactory::ok(self::LABEL)], ['SB-1'])->create(self::SHIPMENT_ID, false, Context::createDefaultContext());
        $this->service($this->shipment(), [ClientFactory::ok(self::LABEL)], null)->create(self::SHIPMENT_ID, false, Context::createDefaultContext());

        static::assertSame([], $this->deliveryUpdates);
    }

    public function testUnknownOrAlreadyBookedShipmentsAreRefused(): void
    {
        $this->assertShipmentException('not_found', fn () => $this->service($this->shipment(), [], null, false)->create(self::SHIPMENT_ID, false, Context::createDefaultContext()));
        $this->assertShipmentException('not_bookable', fn () => $this->service($this->shipment(['status' => 'pending']), [], null, 'pending')->create(self::SHIPMENT_ID, false, Context::createDefaultContext()));
    }

    public function testASpentTokenIsRequotedAndAskedFor(): void
    {
        $this->quoteService->expects(static::once())->method('requote')->willReturn($this->quote());
        $spent = ClientFactory::json(['status' => 'failed', 'message' => 'Invalid request token', 'errors' => ['Invalid request token']], 422);

        $result = $this->service($this->shipment(), [$spent])->create(self::SHIPMENT_ID, false, Context::createDefaultContext());

        static::assertFalse($result->booked);
        static::assertSame(450.0, $result->oldCarrierPrice);
        static::assertSame(480.0, $result->newCarrierPrice);
        static::assertSame('One', $result->courierName);
        static::assertSame('NGN', $result->currency);
        // The customer's courier is kept, at the new token and price.
        static::assertSame('needs_quote', $this->shipmentUpdates[0]['status']);
        static::assertSame('new-token', $this->shipmentUpdates[0]['requestToken']);
        static::assertSame('s1', $this->shipmentUpdates[0]['serviceCode']);
        static::assertSame(480.0, $this->shipmentUpdates[0]['quotedAmount']);
        static::assertSame([], $this->events);
    }

    public function testNoEventWithoutTheOrder(): void
    {
        $this->service($this->shipment(), [ClientFactory::ok(self::LABEL)], [], null, false)->create(self::SHIPMENT_ID, false, Context::createDefaultContext());

        static::assertSame([], $this->events);
    }

    public function testAnOldTokenOrNeedsQuoteIsRequotedWithoutTryingIt(): void
    {
        $this->quoteService->expects(static::exactly(3))->method('requote')->willReturn($this->quote());

        foreach ([
            ['quotedAt' => new \DateTimeImmutable('-200 hours')],
            ['requestToken' => null],
            ['status' => 'needs_quote', 'quotedAt' => new \DateTimeImmutable('-1 hour')],
        ] as $data) {
            $result = $this->service($this->shipment($data), [], null, $data['status'] ?? 'quoted')->create(self::SHIPMENT_ID, false, Context::createDefaultContext());
            static::assertFalse($result->booked);
        }
    }

    public function testConfirmationBooksTheQuoteTheMerchantSaw(): void
    {
        $this->quoteService->expects(static::never())->method('requote');
        $label = ClientFactory::ok(self::LABEL);
        $shipment = $this->shipment(['status' => 'needs_quote', 'requestToken' => 'new-token', 'quotedAt' => new \DateTimeImmutable('-2 minutes')]);

        $result = $this->service($shipment, [$label], [], 'needs_quote')->create(self::SHIPMENT_ID, true, Context::createDefaultContext());

        static::assertTrue($result->booked);
        static::assertSame('new-token', json_decode($label->getRequestOptions()['body'], true)['request_token']);
    }

    public function testConfirmingAStaleOrSpentQuoteAsksAgain(): void
    {
        $this->quoteService->expects(static::exactly(2))->method('requote')->willReturn($this->quote());
        $spent = ClientFactory::json(['status' => 'failed', 'message' => 'Invalid request token'], 422);

        $fresh = $this->shipment(['status' => 'needs_quote', 'requestToken' => 'new-token', 'quotedAt' => new \DateTimeImmutable('-2 minutes')]);
        static::assertFalse($this->service($fresh, [$spent], [], 'needs_quote')->create(self::SHIPMENT_ID, true, Context::createDefaultContext())->booked);

        $stale = $this->shipment(['status' => 'needs_quote', 'requestToken' => 'new-token', 'quotedAt' => new \DateTimeImmutable('-1 hour')]);
        static::assertFalse($this->service($stale, [], [], 'needs_quote')->create(self::SHIPMENT_ID, true, Context::createDefaultContext())->booked);
    }

    public function testOtherBookingErrorsAreStoredAndShown(): void
    {
        $empty = ClientFactory::json(['status' => 'failed', 'message' => 'Insufficient wallet balance'], 400);
        $service = $this->service($this->shipment(), [$empty]);

        try {
            $service->create(self::SHIPMENT_ID, false, Context::createDefaultContext());
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertSame(ApiErrorReason::InsufficientBalance, $e->getReason());
        }

        static::assertSame(['id' => self::SHIPMENT_ID, 'errorCode' => 'insufficient_balance', 'errorMessage' => 'Insufficient wallet balance'], $this->shipmentUpdates[0]);
        static::assertInstanceOf(ShipmentFailedEvent::class, $this->events[0]);
        static::assertSame('Insufficient wallet balance', $this->events[0]->getValues()['errorMessage']);
    }

    public function testErrorsWhenBookingAConfirmedQuoteAreStoredToo(): void
    {
        $shipment = $this->shipment(['status' => 'needs_quote', 'requestToken' => 'new-token', 'quotedAt' => new \DateTimeImmutable()]);

        $this->expectException(ShipbubbleApiException::class);

        try {
            $this->service($shipment, [new MockResponse('', ['http_code' => 503])], [], 'needs_quote')->create(self::SHIPMENT_ID, true, Context::createDefaultContext());
        } finally {
            static::assertSame('courier_unavailable', $this->shipmentUpdates[0]['errorCode']);
        }
    }

    public function testRequoteFailures(): void
    {
        $this->quoteService->method('requote')->willReturn(QuoteError::NoCouriers);
        $old = ['quotedAt' => new \DateTimeImmutable('-200 hours')];

        $this->assertShipmentException('quote_no_couriers', fn () => $this->service($this->shipment($old))->create(self::SHIPMENT_ID, false, Context::createDefaultContext()));
        $this->assertShipmentException('quote_incomplete_address', fn () => $this->service($this->shipment($old), [], [], 'quoted', false)->create(self::SHIPMENT_ID, false, Context::createDefaultContext()));
    }

    public function testRequotePicksTheCheapestWhenTheCourierIsGone(): void
    {
        $this->quoteService->method('requote')->willReturn($this->quote());

        $this->service($this->shipment(['serviceCode' => 'gone', 'requestToken' => null]))->create(self::SHIPMENT_ID, false, Context::createDefaultContext());

        static::assertSame('c2', $this->shipmentUpdates[0]['courierId']);
    }

    public function testRefreshAppliesTheShipbubbleStatus(): void
    {
        $this->stateUpdater->expects(static::once())->method('apply')->with(ShipmentStatus::Completed, self::DELIVERY_ID);
        $label = ClientFactory::ok([['order_id' => 'SB-1', 'status' => 'completed', 'courier' => ['name' => 'One', 'tracking_code' => 'SB777'], 'waybill_document' => 'https://w/1.pdf']]);

        $this->service($this->shipment(['status' => 'in_transit', 'shipbubbleOrderId' => 'SB-1', 'trackingUrl' => 'https://t/1']), [$label])
            ->refresh(self::SHIPMENT_ID, Context::createDefaultContext());

        $update = $this->shipmentUpdates[0];
        static::assertSame('completed', $update['status']);
        static::assertArrayHasKey('deliveredAt', $update);
        static::assertSame('https://t/1', $update['trackingUrl']);
        static::assertSame('https://w/1.pdf', $update['waybillUrl']);
        static::assertSame(['courierTrackingCode' => 'SB777'], $update['lastPayload']);
    }

    public function testRefreshOfACancelledLabelAndUnchangedOrUnknownStatus(): void
    {
        $this->stateUpdater->expects(static::once())->method('apply')->with(ShipmentStatus::Cancelled);
        $booked = ['status' => 'pending', 'shipbubbleOrderId' => 'SB-1'];

        $this->service($this->shipment($booked), [ClientFactory::ok([['order_id' => 'SB-1', 'status' => 'cancelled']])])->refresh(self::SHIPMENT_ID, Context::createDefaultContext());
        static::assertArrayHasKey('cancelledAt', $this->shipmentUpdates[0]);

        $this->shipmentUpdates = [];
        $this->service($this->shipment($booked), [ClientFactory::ok([['order_id' => 'SB-1', 'status' => 'pending']])])->refresh(self::SHIPMENT_ID, Context::createDefaultContext());
        $this->service($this->shipment($booked), [ClientFactory::ok([['order_id' => 'SB-1', 'status' => 'lost_in_space']])])->refresh(self::SHIPMENT_ID, Context::createDefaultContext());
        static::assertArrayNotHasKey('status', $this->shipmentUpdates[1]);
    }

    public function testRefreshNeedsABookedShipmentThatShipbubbleKnows(): void
    {
        $this->assertShipmentException('not_booked', fn () => $this->service($this->shipment())->refresh(self::SHIPMENT_ID, Context::createDefaultContext()));
        $this->assertShipmentException('not_found', fn () => $this->service($this->shipment(['shipbubbleOrderId' => 'SB-1']), [ClientFactory::ok([])])->refresh(self::SHIPMENT_ID, Context::createDefaultContext()));
        $this->assertShipmentException('not_found', fn () => $this->service(null)->refresh(self::SHIPMENT_ID, Context::createDefaultContext()));
    }

    public function testCancel(): void
    {
        $this->stateUpdater->expects(static::once())->method('apply')->with(ShipmentStatus::Cancelled, self::DELIVERY_ID);
        $cancel = ClientFactory::ok([]);

        $this->service($this->shipment(['status' => 'pending', 'shipbubbleOrderId' => 'SB-1']), [$cancel])->cancel(self::SHIPMENT_ID, Context::createDefaultContext());

        static::assertStringEndsWith('/shipping/labels/cancel/SB-1', $cancel->getRequestUrl());
        static::assertSame('cancelled', $this->shipmentUpdates[0]['status']);
        static::assertArrayHasKey('cancelledAt', $this->shipmentUpdates[0]);
    }

    public function testRefusedCancelKeepsTheStatusAndShowsShipbubblesReason(): void
    {
        $this->stateUpdater->expects(static::never())->method('apply');
        $refused = ClientFactory::json(['status' => 'failed', 'message' => 'Shipment label already processed'], 400);

        try {
            $this->service($this->shipment(['status' => 'pending', 'shipbubbleOrderId' => 'SB-1']), [$refused])->cancel(self::SHIPMENT_ID, Context::createDefaultContext());
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertSame('Shipment label already processed', $e->getMessage());
        }

        static::assertSame('Shipment label already processed', $this->shipmentUpdates[0]['errorMessage']);
        static::assertArrayNotHasKey('status', $this->shipmentUpdates[0]);
    }

    public function testOnlyBookedOpenShipmentsCanBeCancelled(): void
    {
        $this->assertShipmentException('not_cancellable', fn () => $this->service($this->shipment())->cancel(self::SHIPMENT_ID, Context::createDefaultContext()));
        $this->assertShipmentException('not_cancellable', fn () => $this->service($this->shipment(['status' => 'completed', 'shipbubbleOrderId' => 'SB-1']))->cancel(self::SHIPMENT_ID, Context::createDefaultContext()));
    }

    private function assertShipmentException(string $reason, \Closure $action): void
    {
        try {
            $action();
            static::fail('Expected ShipmentException ' . $reason);
        } catch (ShipmentException $e) {
            static::assertSame($reason, $e->reason);
            static::assertNotSame('', $e->getMessage());
        }
    }

    private function quote(): QuoteStruct
    {
        return new QuoteStruct([
            new CourierOption('c2', 'Two', 's2', 400.0, 400.0, '1 day', null),
            new CourierOption('c1', 'One', 's1', 480.0, 480.0, '2 days', null),
        ], 'new-token', 'NGN', 'sandbox', new \DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function shipment(array $data = []): ShipmentEntity
    {
        $shipment = new ShipmentEntity();
        $shipment->setId(self::SHIPMENT_ID);
        $shipment->assign(array_merge([
            'orderId' => 'order-1',
            'orderDeliveryId' => self::DELIVERY_ID,
            'salesChannelId' => 'sc-1',
            'mode' => 'sandbox',
            'status' => 'quoted',
            'courierId' => 'c1',
            'courierName' => 'One',
            'serviceCode' => 's1',
            'requestToken' => 'tok',
            'quotedAt' => new \DateTimeImmutable('-1 hour'),
            'quotedAmount' => 450.0,
            'currency' => 'NGN',
        ], $data));

        return $shipment;
    }

    /**
     * @param list<MockResponse> $responses
     * @param list<string>|null $trackingCodes null = the delivery is gone
     */
    private function service(
        ?ShipmentEntity $shipment,
        array $responses = [],
        ?array $trackingCodes = [],
        string|false|null $lockedStatus = null,
        bool $withOrder = true,
    ): ShipmentService {
        $shipments = $this->createMock(EntityRepository::class);
        $shipments->method('search')->willReturnCallback(fn (): EntitySearchResult => $this->searchResult(new ShipmentCollection($shipment !== null ? [$shipment] : [])));
        $shipments->method('update')->willReturnCallback(function (array $payload): EntityWrittenContainerEvent {
            $this->shipmentUpdates[] = $payload[0];

            return self::written();
        });

        $orders = $this->createMock(EntityRepository::class);
        $orders->method('search')->willReturnCallback(fn (): EntitySearchResult => $this->searchResult(new OrderCollection($withOrder ? [$this->order()] : [])));

        $deliveries = $this->createMock(EntityRepository::class);
        $deliveries->method('search')->willReturnCallback(function () use ($trackingCodes): EntitySearchResult {
            $delivery = new OrderDeliveryEntity();
            $delivery->setId(self::DELIVERY_ID);
            $delivery->setTrackingCodes($trackingCodes ?? []);

            return $this->searchResult(new OrderDeliveryCollection($trackingCodes !== null ? [$delivery] : []));
        });
        $deliveries->method('update')->willReturnCallback(function (array $payload): EntityWrittenContainerEvent {
            $this->deliveryUpdates[] = $payload[0];

            return self::written();
        });

        $connection = $this->createMock(Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (\Closure $callback): mixed => $callback());
        $connection->method('fetchOne')->willReturn($lockedStatus ?? $shipment?->getStatus() ?? false);

        $config = ClientFactory::config(['mode' => 'sandbox', 'sandboxApiKey' => ClientFactory::API_KEY]);

        return new ShipmentService(
            $shipments,
            $orders,
            $deliveries,
            new ShipbubbleClient(new ShipbubbleHttpClient($config, new MockHttpClient($responses))),
            $this->quoteService,
            $this->stateUpdater,
            $connection,
            $this->dispatcher,
            new NullLogger(),
        );
    }

    private static function written(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
    }

    private function order(): OrderEntity
    {
        $address = new OrderAddressEntity();
        $address->setId('address-1');
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(self::DELIVERY_ID);
        $delivery->setShippingOrderAddress($address);
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        return $order;
    }

    /**
     * @template T of EntityCollection
     *
     * @param T $collection
     *
     * @return EntitySearchResult<T>
     */
    private function searchResult(EntityCollection $collection): EntitySearchResult
    {
        return new EntitySearchResult('entity', $collection->count(), $collection, null, new Criteria(), Context::createDefaultContext());
    }
}
