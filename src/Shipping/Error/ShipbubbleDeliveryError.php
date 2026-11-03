<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping\Error;

use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Shopware\Core\Checkout\Cart\Error\Error;

/**
 * Blocking cart error when Shipbubble delivery cannot be priced (spec §3).
 * The order must not go through with a wrong or zero shipping price; the
 * customer can still pick another delivery method.
 *
 * The storefront shows `checkout.kmh-shipbubble-<reason>`; the messages below
 * are the API fallback and must match the en-GB snippets.
 */
class ShipbubbleDeliveryError extends Error
{
    private const MESSAGES = [
        'incomplete_address' => 'Please complete your delivery address, including a phone number, to use courier delivery.',
        'invalid_address' => 'We could not find your delivery address for courier delivery. Please check it or choose another delivery method.',
        'unavailable' => 'Courier delivery is temporarily unavailable. Please choose another delivery method.',
        'no_couriers' => 'No courier delivers to your address. Please choose another delivery method.',
        'currency_mismatch' => 'Courier delivery is not available in this currency. Please choose another delivery method.',
        'not_configured' => 'Courier delivery is currently unavailable. Please choose another delivery method.',
    ];

    public function __construct(private readonly QuoteError $reason)
    {
        parent::__construct(self::MESSAGES[$reason->value]);
    }

    public function getId(): string
    {
        return $this->getMessageKey();
    }

    public function getMessageKey(): string
    {
        return 'kmh-shipbubble-' . $this->reason->value;
    }

    public function getLevel(): int
    {
        return self::LEVEL_ERROR;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return [];
    }

    public function getReason(): QuoteError
    {
        return $this->reason;
    }
}
