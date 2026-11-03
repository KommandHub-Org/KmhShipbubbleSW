<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipment\Service;

/**
 * Outcome of "Create shipment": booked, or a fresh carrier price the merchant
 * has to confirm first (spec §12). The customer's charged price never changes.
 */
final class CreateResult
{
    private function __construct(
        public readonly bool $booked,
        public readonly ?float $oldCarrierPrice = null,
        public readonly ?float $newCarrierPrice = null,
        public readonly ?string $courierName = null,
        public readonly ?string $currency = null,
    ) {
    }

    public static function booked(): self
    {
        return new self(true);
    }

    public static function confirmationNeeded(?float $oldCarrierPrice, float $newCarrierPrice, string $courierName, string $currency): self
    {
        return new self(false, $oldCarrierPrice, $newCarrierPrice, $courierName, $currency);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'booked' => $this->booked,
            'oldCarrierPrice' => $this->oldCarrierPrice,
            'newCarrierPrice' => $this->newCarrierPrice,
            'courierName' => $this->courierName,
            'currency' => $this->currency,
        ];
    }
}
