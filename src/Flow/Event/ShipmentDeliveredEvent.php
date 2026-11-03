<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

/**
 * The parcel was delivered (Shipbubble status completed).
 */
class ShipmentDeliveredEvent extends ShipmentEvent
{
    public const EVENT_NAME = 'kmh_shipbubble.shipment.delivered';

    public function getName(): string
    {
        return self::EVENT_NAME;
    }
}
