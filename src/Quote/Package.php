<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

/**
 * The parcel part of a rate request (spec §6), in Shipbubble field names.
 */
final class Package
{
    /**
     * @param list<array{name: string, description: string, unit_weight: string, unit_amount: string, quantity: string}> $items
     * @param array{length: float, width: float, height: float} $dimension
     */
    public function __construct(
        public readonly array $items,
        public readonly array $dimension,
        public readonly string $categoryId,
    ) {
    }

    /**
     * Changes whenever anything that affects the price changes, and only then.
     */
    public function signature(): string
    {
        return hash('sha256', (string)json_encode([$this->items, $this->dimension, $this->categoryId]));
    }
}
