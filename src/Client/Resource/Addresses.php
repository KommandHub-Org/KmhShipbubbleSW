<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;

/**
 * Address validation. Each successful call costs the merchant one wallet
 * token, so callers cache the returned address code.
 */
class Addresses extends ApiResource
{
    /**
     * @throws ShipbubbleApiException reason Validation when the address cannot be used
     */
    public function validate(
        string $name,
        string $email,
        string $phone,
        string $address,
        ?string $postalCode = null,
        ?string $salesChannelId = null,
    ): ValidatedAddress {
        $payload = ['name' => $name, 'email' => $email, 'phone' => $phone, 'address' => $address];

        if ($postalCode !== null && $postalCode !== '') {
            $payload['postal_code'] = $postalCode;
        }

        return ValidatedAddress::fromArray($this->data($this->httpClient->post('/shipping/address/validate', $payload, $salesChannelId)));
    }
}
