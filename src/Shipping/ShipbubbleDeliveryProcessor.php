<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping;

use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\QuoteService;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Courier\CourierChoiceStorage;
use Kommandhub\ShipbubbleSW\Shipping\Error\ShipbubbleDeliveryError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\PercentageTaxRuleBuilder;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Sets the Shipbubble courier price as the shipping cost, only when the
 * Shipbubble method is selected (spec §1).
 *
 * Runs after the core delivery processor (-5000), so the deliveries already
 * exist and only their cost is replaced. Every cart change re-runs it; the
 * quote cache keeps that from calling Shipbubble again for an unchanged cart.
 */
class ShipbubbleDeliveryProcessor implements CartProcessorInterface
{
    /**
     * Set when the Administration recalculates a placed order: the charged
     * shipping price must not change then.
     */
    public const SKIP_DELIVERY_PRICE_RECALCULATION = 'skipDeliveryPriceRecalculation';

    public function __construct(
        private readonly QuoteService $quoteService,
        private readonly CourierChoiceStorage $courierChoiceStorage,
        private readonly Config $config,
        private readonly QuantityPriceCalculator $priceCalculator,
        private readonly PercentageTaxRuleBuilder $taxRuleBuilder,
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $shippingMethod = $context->getShippingMethod();

        if ($shippingMethod->getTechnicalName() !== ShipbubbleShippingMethod::TECHNICAL_NAME
            || $behavior->hasPermission(self::SKIP_DELIVERY_PRICE_RECALCULATION)
            || $toCalculate->getDeliveries()->count() === 0
        ) {
            return;
        }

        $quote = $this->quoteService->quote($toCalculate, $context);

        if ($quote instanceof QuoteError) {
            $fallbackPrice = $this->config->getFloat('fallbackPrice', 0.0, $context->getSalesChannelId());

            if ($quote !== QuoteError::Unavailable || $fallbackPrice <= 0) {
                $toCalculate->addErrors(new ShipbubbleDeliveryError($quote));

                return;
            }

            $quote = QuoteStruct::fallback($context->getCurrency()->getIsoCode(), $this->config->getMode($context->getSalesChannelId()), new \DateTimeImmutable());
            $price = $fallbackPrice;
        } else {
            $quote = $quote->withSelection($this->courierChoiceStorage->load($context));
            $price = $quote->selected->price ?? 0.0;
        }

        $shippingCosts = $this->buildPrice($price, $shippingMethod, $toCalculate, $context);

        foreach ($toCalculate->getDeliveries() as $delivery) {
            $delivery->setShippingCosts($shippingCosts);
            $delivery->addExtension(QuoteStruct::EXTENSION_KEY, $quote);
        }
    }

    /**
     * The courier total includes VAT, so it is used as the gross price and taxed
     * by the shipping method's tax type, like the core delivery processor does.
     */
    private function buildPrice(float $price, ShippingMethodEntity $shippingMethod, Cart $cart, SalesChannelContext $context): CalculatedPrice
    {
        $lineItemPrices = $cart->getLineItems()->getPrices();

        $rules = match ($shippingMethod->getTaxType()) {
            ShippingMethodEntity::TAX_TYPE_HIGHEST => $lineItemPrices->getHighestTaxRule(),
            ShippingMethodEntity::TAX_TYPE_FIXED => $shippingMethod->getTaxId() !== null
                ? $context->buildTaxRules($shippingMethod->getTaxId())
                : $this->taxRuleBuilder->buildCollectionRules($lineItemPrices->getCalculatedTaxes(), $lineItemPrices->getTotalPriceAmount()),
            default => $this->taxRuleBuilder->buildCollectionRules($lineItemPrices->getCalculatedTaxes(), $lineItemPrices->getTotalPriceAmount()),
        };

        return $this->priceCalculator->calculate(new QuantityPriceDefinition($price, $rules, 1), $context);
    }
}
