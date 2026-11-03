<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipment;

/**
 * Lifecycle of one Shipbubble delivery (spec §4).
 *
 * `quoted` and `needs_quote` are ours (before anything is booked); the rest
 * mirror Shipbubble's statuses, plus `failed` for a booking that could not be
 * created.
 */
enum ShipmentStatus: string
{
    case Quoted = 'quoted';
    case NeedsQuote = 'needs_quote';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
