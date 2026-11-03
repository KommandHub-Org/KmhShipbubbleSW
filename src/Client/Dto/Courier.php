<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * One entry of `GET /shipping/couriers`, used for the merchant's courier filter.
 */
final class Courier
{
    public function __construct(
        public readonly string $serviceCode,
        public readonly string $name,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $serviceCode = ArrayReader::string($data, 'service_code');

        return new self($serviceCode, ArrayReader::nullableString($data, 'name') ?? $serviceCode);
    }
}
