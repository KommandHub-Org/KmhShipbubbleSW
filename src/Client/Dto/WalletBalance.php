<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * Result of `GET /shipping/wallet/balance`. Shipments are paid from this wallet.
 */
final class WalletBalance
{
    public function __construct(
        public readonly float $balance,
        public readonly string $currency,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(ArrayReader::float($data, 'balance'), ArrayReader::string($data, 'currency'));
    }
}
