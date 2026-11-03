<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipment\Subscriber;

use Kommandhub\ShipbubbleSW\Core\Content\Shipment\OrderDeliveryExtension;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Defaults;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Writes the chosen courier onto the order as it is placed (spec §2): the
 * quote on each cart delivery becomes a `kmh_shipbubble_shipment` row in the
 * same write as the order, so an order never exists without it.
 *
 * Deliveries are matched by position: the order converter builds them in the
 * order of the cart's deliveries.
 */
class ShipmentQuoteSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CartConvertedEvent::class => 'onCartConverted'];
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $converted = $event->getConvertedCart();
        $orderId = $converted['id'] ?? null;

        if (!is_string($orderId) || !is_array($converted['deliveries'] ?? null)) {
            return;
        }

        $cartDeliveries = array_values($event->getCart()->getDeliveries()->getElements());
        $changed = false;

        foreach ($converted['deliveries'] as $index => $delivery) {
            $quote = ($cartDeliveries[$index] ?? null)?->getExtension(QuoteStruct::EXTENSION_KEY);

            if (!$quote instanceof QuoteStruct || !is_array($delivery)) {
                continue;
            }

            $selected = $quote->selected;
            $delivery[OrderDeliveryExtension::PROPERTY] = [
                'orderId' => $orderId,
                'salesChannelId' => $event->getSalesChannelContext()->getSalesChannelId(),
                'mode' => $quote->mode,
                'status' => ($quote->fallback ? ShipmentStatus::NeedsQuote : ShipmentStatus::Quoted)->value,
                'courierId' => $selected?->courierId,
                'courierName' => $selected?->courierName,
                'serviceCode' => $selected?->serviceCode,
                'requestToken' => $quote->requestToken !== '' ? $quote->requestToken : null,
                'quotedAt' => $quote->fetchedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'quotedAmount' => $selected?->carrierTotal,
                'chargedAmount' => $cartDeliveries[$index]->getShippingCosts()->getTotalPrice(),
                'currency' => $quote->currency,
            ];
            $converted['deliveries'][$index] = $delivery;
            $changed = true;
        }

        if ($changed) {
            $event->setConvertedCart($converted);
        }
    }
}
