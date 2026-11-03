<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping;

/**
 * Identity of the plugin's single shipping method (spec §2). The method is
 * found by technical name everywhere; its id is random per installation.
 */
final class ShipbubbleShippingMethod
{
    public const TECHNICAL_NAME = 'kmh_shipbubble_courier';

    public const NAME_EN = 'Courier delivery (Shipbubble)';

    public const NAME_DE = 'Kurierversand (Shipbubble)';
}
