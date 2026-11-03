<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuoteStruct::class)]
#[CoversClass(CourierOption::class)]
class QuoteStructTest extends TestCase
{
    public function testSelectsTheStoredCourierWhenOffered(): void
    {
        $quote = $this->quote()->withSelection('c2:s2');

        static::assertSame('c2:s2', $quote->selected?->getKey());
        static::assertSame('token', $quote->requestToken);
    }

    public function testFallsBackToTheCheapest(): void
    {
        static::assertSame('c1:s1', $this->quote()->withSelection('gone:x')->selected?->getKey());
        static::assertSame('c1:s1', $this->quote()->withSelection(null)->selected?->getKey());
    }

    public function testFallbackQuoteHasNoCouriers(): void
    {
        $quote = QuoteStruct::fallback('NGN', 'live', new \DateTimeImmutable());

        static::assertTrue($quote->fallback);
        static::assertSame([], $quote->couriers);
        static::assertNull($quote->withSelection('c1:s1')->selected);
    }

    private function quote(): QuoteStruct
    {
        return new QuoteStruct([
            new CourierOption('c1', 'One', 's1', 100.0, 110.0, '1 day', null),
            new CourierOption('c2', 'Two', 's2', 200.0, 220.0, '2 days', 'logo.png'),
        ], 'token', 'NGN', 'sandbox', new \DateTimeImmutable());
    }
}
