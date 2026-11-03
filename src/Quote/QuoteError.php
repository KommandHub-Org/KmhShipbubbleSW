<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;

/**
 * Why a cart could not be quoted (spec §3). Each case is a distinct cart error
 * and storefront message.
 */
enum QuoteError: string
{
    case IncompleteAddress = 'incomplete_address';
    case InvalidAddress = 'invalid_address';
    case Unavailable = 'unavailable';
    case NoCouriers = 'no_couriers';
    case CurrencyMismatch = 'currency_mismatch';
    case NotConfigured = 'not_configured';

    /**
     * A failed rate request. A rejected key means the merchant has to fix the
     * configuration; anything else is treated as Shipbubble being unavailable,
     * which is the case the fallback price exists for.
     */
    public static function fromRateFailure(ApiErrorReason $reason): self
    {
        return match ($reason) {
            ApiErrorReason::NotConfigured, ApiErrorReason::Unauthorized => self::NotConfigured,
            default => self::Unavailable,
        };
    }

    /**
     * Problems only the merchant can fix; they get an admin notification.
     */
    public function notifiesAdmin(): bool
    {
        return $this === self::Unavailable || $this === self::NotConfigured;
    }
}
