<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping;

use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\QuoteService;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Courier\CourierChoiceStorage;
use Kommandhub\ShipbubbleSW\Shipping\Error\ShipbubbleDeliveryError;
use Kommandhub\ShipbubbleSW\Shipping\ShipbubbleDeliveryProcessor;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use Kommandhub\ShipbubbleSW\Tests\Unit\Quote\CheckoutFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\Aggregate\TaxRule\TaxRuleCollection;
use Shopware\Core\Test\Generator;

#[CoversClass(ShipbubbleDeliveryProcessor::class)]
#[UsesClass(Config::class)]
#[UsesClass(QuoteStruct::class)]
#[UsesClass(CourierOption::class)]
#[UsesClass(QuoteError::class)]
#[UsesClass(ShipbubbleDeliveryError::class)]
class ShipbubbleDeliveryProcessorTest extends TestCase
{
    public function testSelectedCourierPriceBecomesTheShippingCost(): void
    {
        $cart = $this->process($this->quote(), 'c2:s2');
        $delivery = $cart->getDeliveries()->first();

        static::assertNotNull($delivery);
        static::assertSame(800.0, $delivery->getShippingCosts()->getTotalPrice());
        $quote = $delivery->getExtension(QuoteStruct::EXTENSION_KEY);
        static::assertInstanceOf(QuoteStruct::class, $quote);
        static::assertSame('c2:s2', $quote->selected?->getKey());
        static::assertCount(0, $cart->getErrors());
    }

    public function testWithoutAStoredChoiceTheCheapestIsCharged(): void
    {
        static::assertSame(500.0, $this->process($this->quote(), null)->getDeliveries()->first()?->getShippingCosts()->getTotalPrice());
    }

    public function testErrorBlocksTheOrder(): void
    {
        $cart = $this->process(QuoteError::NoCouriers, null);

        static::assertTrue($cart->getErrors()->blockOrder());
        static::assertSame('kmh-shipbubble-no_couriers', $cart->getErrors()->first()?->getId());
        static::assertNull($cart->getDeliveries()->first()?->getExtension(QuoteStruct::EXTENSION_KEY));
    }

    public function testUnavailableWithoutFallbackPriceBlocks(): void
    {
        static::assertTrue($this->process(QuoteError::Unavailable, null)->getErrors()->blockOrder());
    }

    public function testUnavailableWithFallbackPriceChargesIt(): void
    {
        $cart = $this->process(QuoteError::Unavailable, null, ['fallbackPrice' => 2500.0]);
        $delivery = $cart->getDeliveries()->first();

        static::assertCount(0, $cart->getErrors());
        static::assertSame(2500.0, $delivery?->getShippingCosts()->getTotalPrice());
        $quote = $delivery->getExtension(QuoteStruct::EXTENSION_KEY);
        static::assertInstanceOf(QuoteStruct::class, $quote);
        static::assertTrue($quote->fallback);
    }

    public function testFallbackPriceDoesNotHideConfigurationErrors(): void
    {
        static::assertTrue($this->process(QuoteError::NotConfigured, null, ['fallbackPrice' => 2500.0])->getErrors()->blockOrder());
    }

    public function testOtherShippingMethodsAreLeftAlone(): void
    {
        $quoteService = $this->createMock(QuoteService::class);
        $quoteService->expects(static::never())->method('quote');
        $cart = Generator::createCartWithDelivery();

        $this->processor($quoteService, null)->process(new CartDataCollection(), $cart, $cart, CheckoutFixture::context(technicalName: 'other'), new CartBehavior());

        static::assertSame(10.0, $cart->getDeliveries()->first()?->getShippingCosts()->getTotalPrice());
    }

    public function testAdminRecalculationAndEmptyCartsAreLeftAlone(): void
    {
        $quoteService = $this->createMock(QuoteService::class);
        $quoteService->expects(static::never())->method('quote');
        $processor = $this->processor($quoteService, null);

        $cart = Generator::createCartWithDelivery();
        $processor->process(new CartDataCollection(), $cart, $cart, CheckoutFixture::context(), new CartBehavior([ShipbubbleDeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true]));

        $empty = new Cart('empty');
        $processor->process(new CartDataCollection(), $empty, $empty, CheckoutFixture::context(), new CartBehavior());

        static::assertSame(10.0, $cart->getDeliveries()->first()?->getShippingCosts()->getTotalPrice());
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function taxTypes(): iterable
    {
        yield 'auto' => [ShippingMethodEntity::TAX_TYPE_AUTO, false];
        yield 'highest' => [ShippingMethodEntity::TAX_TYPE_HIGHEST, false];
        yield 'fixed without tax' => [ShippingMethodEntity::TAX_TYPE_FIXED, false];
        yield 'fixed with tax' => [ShippingMethodEntity::TAX_TYPE_FIXED, true];
    }

    #[DataProvider('taxTypes')]
    public function testEveryTaxTypeKeepsTheGrossPrice(string $taxType, bool $withTax): void
    {
        $context = CheckoutFixture::context();
        $context->getShippingMethod()->setTaxType($taxType);

        if ($withTax) {
            $tax = $context->getTaxRules()->first();
            static::assertNotNull($tax);
            $tax->setRules(new TaxRuleCollection());
            $context->getShippingMethod()->setTaxId($tax->getId());
        }

        static::assertSame(500.0, $this->process($this->quote(), null, [], $context)->getDeliveries()->first()?->getShippingCosts()->getTotalPrice());
    }

    private function quote(): QuoteStruct
    {
        return new QuoteStruct([
            new CourierOption('c1', 'One', 's1', 450.0, 500.0, '1 day', null),
            new CourierOption('c2', 'Two', 's2', 700.0, 800.0, '2 days', null),
        ], 'tok', 'NGN', 'sandbox', new \DateTimeImmutable());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function process(QuoteStruct|QuoteError $result, ?string $choice, array $config = [], ?SalesChannelContext $context = null): Cart
    {
        $quoteService = $this->createMock(QuoteService::class);
        $quoteService->method('quote')->willReturn($result);
        $cart = Generator::createCartWithDelivery();

        $this->processor($quoteService, $choice, $config)
            ->process(new CartDataCollection(), $cart, $cart, $context ?? CheckoutFixture::context(), new CartBehavior());

        return $cart;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processor(QuoteService $quoteService, ?string $choice, array $config = []): ShipbubbleDeliveryProcessor
    {
        $storage = $this->createMock(CourierChoiceStorage::class);
        $storage->method('load')->willReturn($choice);
        $rounding = new CashRounding();

        return new ShipbubbleDeliveryProcessor(
            $quoteService,
            $storage,
            ClientFactory::config(array_merge(['mode' => 'sandbox'], $config)),
            new QuantityPriceCalculator(new GrossPriceCalculator(new TaxCalculator(), $rounding), new NetPriceCalculator(new TaxCalculator(), $rounding)),
            new PercentageTaxRuleBuilder(),
        );
    }
}
