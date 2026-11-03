<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * Booking the label failed (empty wallet, courier unavailable, …). Meant for
 * an admin alert; `errorMessage` carries Shipbubble's reason.
 */
class ShipmentFailedEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.failed';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
