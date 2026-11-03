<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Address\AddressLine;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * The Shipbubble address code of the customer's shipping address (spec §1 step 2).
 *
 * Each validation costs the merchant a wallet token, so results are cached:
 * a valid address for 30 days, an address Shipbubble rejected for an hour.
 */
class ReceiverAddressService
{
    private const VALID_TTL = 2592000;

    private const INVALID_TTL = 3600;

    public function __construct(
        private readonly Config $config,
        private readonly ShipbubbleClient $client,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @throws ShipbubbleApiException when Shipbubble could not be asked (never cached)
     */
    public function addressCode(SalesChannelContext $context): string|QuoteError
    {
        $address = $context->getShippingLocation()->getAddress();

        if ($address === null) {
            return QuoteError::IncompleteAddress;
        }

        $street = trim($address->getStreet());
        $city = trim($address->getCity());
        $phone = trim((string)$address->getPhoneNumber());
        $country = $address->getCountry()?->getIso() ?? '';
        $email = (string)$context->getCustomer()?->getEmail();

        if ($street === '' || $city === '' || $phone === '' || $country === '' || $email === '') {
            return QuoteError::IncompleteAddress;
        }

        $name = trim($address->getFirstName() . ' ' . $address->getLastName());
        $line = AddressLine::build([
            $street,
            (string)$address->getAdditionalAddressLine1(),
            $city,
            (string)$address->getCountryState()?->getName(),
        ], $country);
        $salesChannelId = $context->getSalesChannelId();
        $key = 'kmh_sb_addr_' . $this->config->getMode($salesChannelId) . '_'
            . hash('sha256', mb_strtolower(implode('|', [$line, (string)$address->getZipcode(), $name, $email, $phone])));

        $code = $this->cache->get($key, function (ItemInterface $item) use ($name, $email, $phone, $line, $address, $salesChannelId): string {
            try {
                $validated = $this->client->addresses()->validate($name, $email, $phone, $line, $address->getZipcode(), $salesChannelId);
            } catch (ShipbubbleApiException $e) {
                if ($e->getReason() !== ApiErrorReason::Validation && $e->getReason() !== ApiErrorReason::BadRequest) {
                    throw $e;
                }
                $validated = null;
            }

            // An empty code marks an address Shipbubble could not validate.
            $code = $validated === null ? '' : $validated->addressCode;
            $item->expiresAfter($code === '' ? self::INVALID_TTL : self::VALID_TTL);

            return $code;
        });

        return $code === '' ? QuoteError::InvalidAddress : $code;
    }
}
