<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The parcel is on its way to the customer.
 */
class ShipmentInTransitEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.in_transit';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
