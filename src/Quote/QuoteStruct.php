<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Shopware\Core\Framework\Struct\Struct;

/**
 * A Shipbubble quote for one cart, attached to the cart delivery as
 * `kmhShipbubbleQuote`. The storefront renders the courier picker from it and
 * the order conversion persists the selected courier from it.
 *
 * A fallback quote (Shipbubble unavailable, merchant fallback price) has no
 * couriers and no selection.
 */
final class QuoteStruct extends Struct
{
    public const EXTENSION_KEY = 'kmhShipbubbleQuote';

    /**
     * @param list<CourierOption> $couriers cheapest first
     */
    public function __construct(
        public readonly array $couriers,
        public readonly string $requestToken,
        public readonly string $currency,
        public readonly string $mode,
        public readonly \DateTimeImmutable $fetchedAt,
        public readonly ?CourierOption $selected = null,
        public readonly bool $fallback = false,
    ) {
    }

    public static function fallback(string $currency, string $mode, \DateTimeImmutable $now): self
    {
        return new self([], '', $currency, $mode, $now, null, true);
    }

    /**
     * The stored courier if this quote still offers it, otherwise the cheapest.
     */
    public function withSelection(?string $courierKey): self
    {
        $selected = $this->couriers[0] ?? null;

        foreach ($this->couriers as $courier) {
            if ($courier->getKey() === $courierKey) {
                $selected = $courier;
                break;
            }
        }

        return new self($this->couriers, $this->requestToken, $this->currency, $this->mode, $this->fetchedAt, $selected, $this->fallback);
    }
}
