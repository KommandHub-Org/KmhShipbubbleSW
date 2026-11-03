<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping\Sender;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;

/**
 * Validates the sales channel's sender address with Shipbubble and stores the
 * resulting address code for the current mode (spec §5). Rate requests refer
 * to the sender only by this code.
 */
class SenderAddressService
{
    public function __construct(
        private readonly Config $config,
        private readonly ShipbubbleClient $client,
    ) {
    }

    /**
     * @throws ShipbubbleApiException reason Validation when the sender details are incomplete or rejected
     */
    public function validateAndStore(?string $salesChannelId = null): ValidatedAddress
    {
        $name = $this->config->getString('senderName', $salesChannelId);
        $phone = $this->config->getString('senderPhone', $salesChannelId);
        $email = $this->config->getString('senderEmail', $salesChannelId);
        $street = $this->config->getString('senderAddress', $salesChannelId);

        if ($name === '' || $phone === '' || $email === '' || $street === '') {
            throw new ShipbubbleApiException(ApiErrorReason::Validation, 'Sender name, phone, email and address are required.');
        }

        // Shipbubble geocodes a single address line; give it every part we have.
        $address = implode(', ', array_filter([
            $street,
            $this->config->getString('senderCity', $salesChannelId),
            $this->config->getString('senderState', $salesChannelId),
            $this->config->getString('senderCountry', $salesChannelId) ?: 'NG',
        ], static fn (string $part): bool => $part !== ''));

        $validated = $this->client->addresses()->validate($name, $email, $phone, $address, null, $salesChannelId);

        if ($validated->addressCode === '') {
            throw new ShipbubbleApiException(ApiErrorReason::InvalidResponse, 'Shipbubble did not return an address code for the sender.');
        }

        $this->config->setSenderAddressCode($validated->addressCode, $salesChannelId);

        return $validated;
    }
}
