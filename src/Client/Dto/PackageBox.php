<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * One standard box of `GET /shipping/labels/boxes`. Dimensions in cm, weight in kg.
 */
final class PackageBox
{
    public function __construct(
        public readonly string $name,
        public readonly float $length,
        public readonly float $width,
        public readonly float $height,
        public readonly float $maxWeight,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ArrayReader::string($data, 'name'),
            ArrayReader::float($data, 'length'),
            ArrayReader::float($data, 'width'),
            ArrayReader::float($data, 'height'),
            ArrayReader::float($data, 'max_weight'),
        );
    }
}
