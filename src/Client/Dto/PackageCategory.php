<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * One entry of `GET /shipping/labels/categories`.
 */
final class PackageCategory
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(ArrayReader::string($data, 'category_id'), ArrayReader::string($data, 'category'));
    }
}
