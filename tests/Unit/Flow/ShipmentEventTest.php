<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Flow;

use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Kommandhub\ShipbubbleSW\Flow\FlowEventSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Shopware\Core\Framework\Event\BusinessEventCollectorResponse;
use Shopware\Core\Framework\Event\BusinessEventDefinition;

#[CoversClass(ShipmentEvent::class)]
#[CoversClass(ShipmentCreatedEvent::class)]
#[CoversClass(ShipmentFailedEvent::class)]
#[CoversClass(FlowEventSubscriber::class)]
class ShipmentEventTest extends TestCase
{
    public function testEventsExposeTheOrderTheCustomerAndTheShipmentValues(): void
    {
        $customer = new OrderCustomerEntity();
        $customer->setEmail('ada@example.com');
        $customer->setFirstName('Ada');
        $customer->setLastName('Obi');
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sc-1');
        $order->setOrderCustomer($customer);
        $context = Context::createDefaultContext();

        $created = new ShipmentCreatedEvent($order, $context, 'One', 'https://t/1', 'pending');
        $failed = new ShipmentFailedEvent($order, $context, 'One', '', 'quoted', 'Wallet empty');

        static::assertSame('kmh_shipbubble.shipment.created', $created->getName());
        static::assertSame('kmh_shipbubble.shipment.failed', $failed->getName());
        static::assertSame($order, $created->getOrder());
        static::assertSame('order-1', $created->getOrderId());
        static::assertSame('sc-1', $created->getSalesChannelId());
        static::assertSame($context, $created->getContext());
        static::assertSame(['ada@example.com' => 'Ada Obi'], $created->getMailStruct()->getRecipients());
        static::assertSame(['courierName' => 'One', 'trackingUrl' => 'https://t/1', 'status' => 'pending', 'errorMessage' => ''], $created->getValues());
        static::assertSame('Wallet empty', $failed->getValues()['errorMessage']);
        static::assertSame(['order', 'courierName', 'trackingUrl', 'status', 'errorMessage'], array_keys(ShipmentEvent::getAvailableData()->toArray()));
    }

    public function testBothEventsAreOfferedToFlowBuilder(): void
    {
        $collector = $this->createMock(BusinessEventCollector::class);
        $collector->method('define')->willReturnCallback(static fn (string $class): ?BusinessEventDefinition => $class === ShipmentCreatedEvent::class
            ? new BusinessEventDefinition('kmh_shipbubble.shipment.created', $class, [])
            : null);
        $event = new BusinessEventCollectorEvent(new BusinessEventCollectorResponse(), Context::createDefaultContext());

        static::assertArrayHasKey(BusinessEventCollectorEvent::NAME, FlowEventSubscriber::getSubscribedEvents());
        (new FlowEventSubscriber($collector))->onCollect($event);

        static::assertSame(['kmh_shipbubble.shipment.created'], array_keys($event->getCollection()->getElements()));
    }
}
