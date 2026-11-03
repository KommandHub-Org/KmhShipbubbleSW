<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipment\Subscriber;

use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Kommandhub\ShipbubbleSW\Shipment\Subscriber\ShipmentQuoteSubscriber;
use Kommandhub\ShipbubbleSW\Tests\Unit\Quote\CheckoutFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Test\Generator;

#[CoversClass(ShipmentQuoteSubscriber::class)]
#[UsesClass(QuoteStruct::class)]
#[UsesClass(CourierOption::class)]
#[UsesClass(ShipmentStatus::class)]
class ShipmentQuoteSubscriberTest extends TestCase
{
    public function testSubscribesToCartConversion(): void
    {
        static::assertArrayHasKey(CartConvertedEvent::class, ShipmentQuoteSubscriber::getSubscribedEvents());
    }

    public function testSelectedCourierIsWrittenWithTheChargedPrice(): void
    {
        $quote = (new QuoteStruct([new CourierOption('c1', 'One', 's1', 450.0, 500.0, '1 day', null)], 'tok', 'NGN', 'sandbox', new \DateTimeImmutable('2026-11-03 10:00:00')))
            ->withSelection(null);
        $shipment = $this->convert($quote)['deliveries'][0]['kmhShipbubbleShipment'];

        static::assertSame('order-1', $shipment['orderId']);
        static::assertSame(ShipmentStatus::Quoted->value, $shipment['status']);
        static::assertSame('c1', $shipment['courierId']);
        static::assertSame('One', $shipment['courierName']);
        static::assertSame('s1', $shipment['serviceCode']);
        static::assertSame('tok', $shipment['requestToken']);
        static::assertSame(450.0, $shipment['quotedAmount']);
        static::assertSame(10.0, $shipment['chargedAmount']);
        static::assertSame('NGN', $shipment['currency']);
        static::assertSame('sandbox', $shipment['mode']);
        static::assertSame('2026-11-03 10:00:00.000', $shipment['quotedAt']);
    }

    public function testFallbackOrdersNeedAQuote(): void
    {
        $shipment = $this->convert(QuoteStruct::fallback('NGN', 'live', new \DateTimeImmutable()))['deliveries'][0]['kmhShipbubbleShipment'];

        static::assertSame(ShipmentStatus::NeedsQuote->value, $shipment['status']);
        static::assertNull($shipment['courierId']);
        static::assertNull($shipment['requestToken']);
    }

    public function testOtherDeliveriesAndPayloadsAreLeftAlone(): void
    {
        $cart = Generator::createCartWithDelivery();
        $converted = ['id' => 'order-1', 'deliveries' => [['id' => 'd1']]];
        static::assertSame($converted, $this->dispatch($cart, $converted));

        static::assertSame(['deliveries' => []], $this->dispatch($cart, ['deliveries' => []]));
        static::assertSame(['id' => 'order-1'], $this->dispatch($cart, ['id' => 'order-1']));
    }

    /**
     * @return array<string, mixed>
     */
    private function convert(QuoteStruct $quote): array
    {
        $cart = Generator::createCartWithDelivery();
        $cart->getDeliveries()->first()?->addExtension(QuoteStruct::EXTENSION_KEY, $quote);

        return $this->dispatch($cart, ['id' => 'order-1', 'deliveries' => [['id' => 'd1']]]);
    }

    /**
     * @param array<string, mixed> $converted
     *
     * @return array<string, mixed>
     */
    private function dispatch(Cart $cart, array $converted): array
    {
        $event = new CartConvertedEvent($cart, $converted, CheckoutFixture::context(), new OrderConversionContext());
        (new ShipmentQuoteSubscriber())->onCartConverted($event);

        return $event->getConvertedCart();
    }
}
