<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The courier picked the parcel up.
 */
class ShipmentPickedUpEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.picked_up';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
