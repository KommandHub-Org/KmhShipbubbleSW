<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * Result of `POST /shipping/address/validate`. The address code is what rate
 * requests refer to.
 */
final class ValidatedAddress
{
    public function __construct(
        public readonly string $addressCode,
        public readonly string $formattedAddress,
        public readonly string $city,
        public readonly string $state,
        public readonly string $countryCode,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ArrayReader::string($data, 'address_code'),
            ArrayReader::string($data, 'formatted_address'),
            ArrayReader::string($data, 'city'),
            ArrayReader::string($data, 'state'),
            ArrayReader::string($data, 'country_code'),
        );
    }
}
