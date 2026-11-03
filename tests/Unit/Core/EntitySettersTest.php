<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Core;

use Kommandhub\ShipbubbleSW\Core\Content\Shipment\ShipmentEntity;
use Kommandhub\ShipbubbleSW\Core\Content\WebhookEvent\WebhookEventEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(ShipmentEntity::class)]
#[CoversClass(WebhookEventEntity::class)]
class EntitySettersTest extends TestCase
{
    /**
     * @return iterable<string, array{object, string, mixed}>
     */
    public static function fields(): iterable
    {
        $date = new \DateTimeImmutable('2026-11-03 12:00:00');
        $shipment = [
            'OrderId' => 'o1', 'OrderVersionId' => 'v1', 'OrderDeliveryId' => 'd1', 'OrderDeliveryVersionId' => 'v2',
            'SalesChannelId' => 'sc', 'Mode' => 'live', 'Status' => 'pending', 'CourierId' => 'c1', 'CourierName' => 'One',
            'ServiceCode' => 's1', 'RequestToken' => 'tok', 'QuotedAt' => $date, 'QuotedAmount' => 1.5, 'ChargedAmount' => 2.5,
            'Currency' => 'NGN', 'ShipbubbleOrderId' => 'SB-1', 'TrackingUrl' => 'https://t', 'WaybillUrl' => 'https://w',
            'Cod' => true, 'InsuranceCode' => 'ins', 'ErrorCode' => 'e', 'ErrorMessage' => 'msg', 'LastPayload' => ['a' => 1],
            'DeliveredAt' => $date, 'CancelledAt' => $date, 'Order' => new OrderEntity(), 'OrderDelivery' => new OrderDeliveryEntity(),
        ];

        foreach ($shipment as $field => $value) {
            yield 'shipment ' . $field => [new ShipmentEntity(), $field, $value];
        }

        $event = [
            'IdempotencyKey' => str_repeat('a', 64), 'Event' => 'shipment.status.changed', 'ShipbubbleOrderId' => 'SB-1',
            'Payload' => ['order_id' => 'SB-1'], 'ReceivedAt' => $date, 'ProcessedAt' => $date, 'Error' => 'note',
        ];

        foreach ($event as $field => $value) {
            yield 'webhook event ' . $field => [new WebhookEventEntity(), $field, $value];
        }
    }

    #[DataProvider('fields')]
    public function testSetterAndGetterRoundTrip(object $entity, string $field, mixed $value): void
    {
        $entity->{'set' . $field}($value);

        static::assertSame($value, $entity->{'get' . $field}());
    }
}
