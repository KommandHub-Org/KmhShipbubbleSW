<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * Result of `POST /shipping/fetch_rates`: the token a shipment is later booked
 * against, and the couriers that offered a price.
 */
final class RateQuote
{
    /**
     * @param list<CourierRate> $couriers
     */
    public function __construct(
        public readonly string $requestToken,
        public readonly array $couriers,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ArrayReader::string($data, 'request_token'),
            array_map(CourierRate::fromArray(...), ArrayReader::rows(ArrayReader::array($data, 'couriers'))),
        );
    }
}
