<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Dto;

/**
 * Defensive reads from decoded Shipbubble JSON.
 *
 * The API documentation is incomplete and examples disagree on types (numbers
 * arrive as strings and vice versa), so every DTO reads through these instead
 * of trusting a shape.
 */
final class ArrayReader
{
    /**
     * @param array<mixed> $data
     */
    public static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? (string)$value : '';
    }

    /**
     * @param array<mixed> $data
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = self::string($data, $key);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<mixed> $data
     */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float)$value : 0.0;
    }

    /**
     * @param array<mixed> $data
     */
    public static function bool(array $data, string $key): bool
    {
        return filter_var($data[$key] ?? false, \FILTER_VALIDATE_BOOL);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function array(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }

    /**
     * The array elements of a list, skipping anything that is not an array.
     *
     * @param array<mixed> $list
     *
     * @return list<array<mixed>>
     */
    public static function rows(array $list): array
    {
        return array_values(array_filter($list, 'is_array'));
    }
}
