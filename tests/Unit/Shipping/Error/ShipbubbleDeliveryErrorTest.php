<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping\Error;

use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Shipping\Error\ShipbubbleDeliveryError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShipbubbleDeliveryError::class)]
#[UsesClass(QuoteError::class)]
class ShipbubbleDeliveryErrorTest extends TestCase
{
    public function testEveryReasonIsABlockingErrorWithItsOwnKey(): void
    {
        foreach (QuoteError::cases() as $reason) {
            $error = new ShipbubbleDeliveryError($reason);

            static::assertSame('kmh-shipbubble-' . $reason->value, $error->getId());
            static::assertSame($error->getId(), $error->getMessageKey());
            static::assertSame(ShipbubbleDeliveryError::LEVEL_ERROR, $error->getLevel());
            static::assertTrue($error->blockOrder());
            static::assertSame([], $error->getParameters());
            static::assertSame($reason, $error->getReason());
            static::assertNotSame('', $error->getMessage());
        }
    }
}
