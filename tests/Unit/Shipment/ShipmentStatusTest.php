<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipment;

use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipmentStatus::class)]
class ShipmentStatusTest extends TestCase
{
    public function testBookableAndOpenStatuses(): void
    {
        $bookable = array_values(array_filter(ShipmentStatus::cases(), static fn (ShipmentStatus $s): bool => $s->isBookable()));
        $open = array_values(array_filter(ShipmentStatus::cases(), static fn (ShipmentStatus $s): bool => $s->isOpen()));

        static::assertSame([ShipmentStatus::Quoted, ShipmentStatus::NeedsQuote], $bookable);
        static::assertSame([ShipmentStatus::Pending, ShipmentStatus::Confirmed, ShipmentStatus::PickedUp, ShipmentStatus::InTransit], $open);
    }
}
