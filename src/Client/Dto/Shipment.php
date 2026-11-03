<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * A Shipbubble shipment ("label"), as returned by create and list calls. Also
 * the tracking view: status, tracking link and waybill.
 */
final class Shipment
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $status,
        public readonly string $courierName,
        public readonly ?string $trackingUrl,
        public readonly ?string $waybillUrl,
        public readonly ?string $courierTrackingCode = null,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        // `courier` is an object ({name, …}) in examples, a plain name in others.
        $courier = $data['courier'] ?? null;
        $courierName = is_array($courier) ? ArrayReader::string($courier, 'name') : ArrayReader::string($data, 'courier');

        return new self(
            ArrayReader::string($data, 'order_id'),
            ArrayReader::string($data, 'status'),
            $courierName,
            ArrayReader::nullableString($data, 'tracking_url'),
            ArrayReader::nullableString($data, 'waybill_document'),
            is_array($courier) ? ArrayReader::nullableString($courier, 'tracking_code') : null,
        );
    }
}
