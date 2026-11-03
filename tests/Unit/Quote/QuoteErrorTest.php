<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuoteError::class)]
#[UsesClass(ApiErrorReason::class)]
class QuoteErrorTest extends TestCase
{
    public function testRateFailureMapping(): void
    {
        static::assertSame(QuoteError::NotConfigured, QuoteError::fromRateFailure(ApiErrorReason::Unauthorized));
        static::assertSame(QuoteError::NotConfigured, QuoteError::fromRateFailure(ApiErrorReason::NotConfigured));
        static::assertSame(QuoteError::Unavailable, QuoteError::fromRateFailure(ApiErrorReason::Timeout));
        static::assertSame(QuoteError::Unavailable, QuoteError::fromRateFailure(ApiErrorReason::Validation));
    }

    public function testOnlyMerchantProblemsNotifyTheAdmin(): void
    {
        static::assertTrue(QuoteError::Unavailable->notifiesAdmin());
        static::assertTrue(QuoteError::NotConfigured->notifiesAdmin());
        static::assertFalse(QuoteError::IncompleteAddress->notifiesAdmin());
        static::assertFalse(QuoteError::NoCouriers->notifiesAdmin());
    }
}
