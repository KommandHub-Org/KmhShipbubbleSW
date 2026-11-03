<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The label was booked at Shipbubble; the tracking link is live.
 */
class ShipmentCreatedEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.created';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
