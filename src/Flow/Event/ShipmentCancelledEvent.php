<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The shipment was cancelled.
 */
class ShipmentCancelledEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.cancelled';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
