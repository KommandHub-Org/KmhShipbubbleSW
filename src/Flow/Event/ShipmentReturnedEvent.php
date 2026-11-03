<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The parcel is being returned to the shop.
 */
class ShipmentReturnedEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.returned';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
