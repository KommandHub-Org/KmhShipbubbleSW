<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Address\AddressLine;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The Shipbubble address code of the customer's shipping address (spec §1 step 2).
 *
 * Shipbubble charges for each validation, and its address codes never expire
 * (Shipbubble support, November 2026), so a code is stored in
 * `kmh_shipbubble_address` for good, per sales channel and mode: sandbox and
 * live codes are separate. An address Shipbubble rejected is only cached, for
 * an hour.
 */
class ReceiverAddressService
{
    private const INVALID_TTL = 3600;

    public function __construct(
        private readonly Config $config,
        private readonly ShipbubbleClient $client,
        private readonly CacheInterface $cache,
        private readonly Connection $connection,
    ) {
    }

    /**
     * The address code of a checkout or order shipping address.
     *
     * @throws ShipbubbleApiException when Shipbubble could not be asked (never cached)
     */
    public function addressCodeFor(CustomerAddressEntity|OrderAddressEntity $address, string $email, string $salesChannelId): string|QuoteError
    {
        $street = trim($address->getStreet());
        $city = trim($address->getCity());
        $phone = trim((string)$address->getPhoneNumber());
        $country = $address->getCountry()?->getIso() ?? '';

        if ($street === '' || $city === '' || $phone === '' || $country === '' || $email === '') {
            return QuoteError::IncompleteAddress;
        }

        $name = self::name($address);
        $line = self::line($address);
        $mode = $this->config->getMode($salesChannelId);
        $hash = self::hash($address, $line, $email);

        $stored = $this->connection->fetchOne(
            'SELECT `address_code` FROM `kmh_shipbubble_address` WHERE `sales_channel_id` = :salesChannelId AND `mode` = :mode AND `address_hash` = :hash',
            ['salesChannelId' => Uuid::fromHexToBytes($salesChannelId), 'mode' => $mode, 'hash' => $hash],
        );

        if (\is_string($stored) && $stored !== '') {
            return $stored;
        }

        $code = $this->cache->get('kmh_sb_addr_' . $salesChannelId . '_' . $mode . '_' . $hash, function (ItemInterface $item, bool &$save) use ($name, $email, $phone, $line, $address, $salesChannelId, $mode, $hash): string {
            try {
                $validated = $this->client->addresses()->validate($name, $email, $phone, $line, $address->getZipcode(), $salesChannelId);
            } catch (ShipbubbleApiException $e) {
                if ($e->getReason() !== ApiErrorReason::Validation && $e->getReason() !== ApiErrorReason::BadRequest) {
                    throw $e;
                }
                $validated = null;
            }

            // An empty code marks an address Shipbubble could not validate;
            // only that is cached. A valid code goes to the table instead.
            $code = $validated === null ? '' : $validated->addressCode;
            $item->expiresAfter(self::INVALID_TTL);
            $save = $code === '';

            if ($code !== '') {
                $this->store($salesChannelId, $mode, $hash, $code);
            }

            return $code;
        });

        return $code === '' ? QuoteError::InvalidAddress : $code;
    }

    /**
     * Drops the stored code of an address, so the next quote validates it
     * again. For a code Shipbubble no longer accepts.
     */
    public function forget(CustomerAddressEntity|OrderAddressEntity $address, string $email, string $salesChannelId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `kmh_shipbubble_address` WHERE `sales_channel_id` = :salesChannelId AND `mode` = :mode AND `address_hash` = :hash',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'mode' => $this->config->getMode($salesChannelId),
                'hash' => self::hash($address, self::line($address), $email),
            ],
        );
    }

    private function store(string $salesChannelId, string $mode, string $hash, string $code): void
    {
        // Two checkouts may validate the same address at once; the later one wins.
        $this->connection->executeStatement(
            'INSERT INTO `kmh_shipbubble_address` (`sales_channel_id`, `mode`, `address_hash`, `address_code`, `created_at`)
                VALUES (:salesChannelId, :mode, :hash, :code, :now)
                ON DUPLICATE KEY UPDATE `address_code` = VALUES(`address_code`)',
            [
                'salesChannelId' => Uuid::fromHexToBytes($salesChannelId),
                'mode' => $mode,
                'hash' => $hash,
                'code' => $code,
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        );
    }

    private static function name(CustomerAddressEntity|OrderAddressEntity $address): string
    {
        return trim($address->getFirstName() . ' ' . $address->getLastName());
    }

    private static function line(CustomerAddressEntity|OrderAddressEntity $address): string
    {
        return AddressLine::build([
            $address->getStreet(),
            (string)$address->getAdditionalAddressLine1(),
            $address->getCity(),
            (string)$address->getCountryState()?->getName(),
        ], $address->getCountry()?->getIso() ?? '');
    }

    /**
     * The code was validated with the receiver's name, email and phone, so
     * they are part of the key. Case and runs of spaces are not: "12  admiralty
     * way" is "12 Admiralty Way", and the merchant does not pay for it twice.
     */
    private static function hash(CustomerAddressEntity|OrderAddressEntity $address, string $line, string $email): string
    {
        $parts = [$line, (string)$address->getZipcode(), self::name($address), $email, (string)$address->getPhoneNumber()];

        return hash('sha256', mb_strtolower((string)preg_replace('/\s+/u', ' ', implode('|', array_map(trim(...), $parts)))));
    }
}
