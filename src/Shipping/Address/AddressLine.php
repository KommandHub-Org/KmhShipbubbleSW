<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping\Address;

/**
 * Builds the single address line Shipbubble geocodes. Used for the sender
 * (config) and the receiver (checkout address) alike.
 */
final class AddressLine
{
    /**
     * @param list<string> $parts street, city, state, … in that order; empty parts are skipped
     */
    public static function build(array $parts, string $country): string
    {
        $parts[] = self::countryName($country);

        return implode(', ', array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * Shipbubble's geocoder rejects an ISO code ("NG") but accepts the English
     * country name ("Nigeria"). Anything that is not a two-letter code is passed on.
     */
    public static function countryName(string $country): string
    {
        if (preg_match('/^[A-Za-z]{2}$/', $country) !== 1) {
            return $country;
        }

        return \Locale::getDisplayRegion('-' . $country, 'en') ?: $country;
    }
}
