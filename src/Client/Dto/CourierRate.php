<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * One courier offer in a rate quote. `total` already includes VAT and is the
 * carrier price before any merchant markup.
 */
final class CourierRate
{
    public function __construct(
        public readonly string $courierId,
        public readonly string $courierName,
        public readonly string $serviceCode,
        public readonly float $total,
        public readonly string $currency,
        public readonly string $deliveryEta,
        public readonly ?string $courierImage,
        public readonly bool $codAvailable,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ArrayReader::string($data, 'courier_id'),
            ArrayReader::string($data, 'courier_name'),
            ArrayReader::string($data, 'service_code'),
            ArrayReader::float($data, 'total'),
            ArrayReader::string($data, 'currency'),
            ArrayReader::string($data, 'delivery_eta'),
            ArrayReader::nullableString($data, 'courier_image'),
            ArrayReader::bool($data, 'is_cod_available'),
        );
    }
}
