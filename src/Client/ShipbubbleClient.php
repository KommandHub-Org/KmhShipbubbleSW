<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client;

use Kommandhub\ShipbubbleSW\Client\Http\HttpClientInterface;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\Couriers;
use Kommandhub\ShipbubbleSW\Client\Resource\Labels;
use Kommandhub\ShipbubbleSW\Client\Resource\Rates;
use Kommandhub\ShipbubbleSW\Client\Resource\Wallet;

/**
 * Facade over the Shipbubble API resources.
 *
 * Services depend on this one class rather than on each resource class, which
 * keeps constructor signatures stable as the API surface grows. Tests fake the
 * transport (HttpClientInterface), not this facade.
 */
class ShipbubbleClient
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function addresses(): Addresses
    {
        return new Addresses($this->httpClient);
    }

    public function rates(): Rates
    {
        return new Rates($this->httpClient);
    }

    public function couriers(): Couriers
    {
        return new Couriers($this->httpClient);
    }

    public function labels(): Labels
    {
        return new Labels($this->httpClient);
    }

    public function wallet(): Wallet
    {
        return new Wallet($this->httpClient);
    }
}
