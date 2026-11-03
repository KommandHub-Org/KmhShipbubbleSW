<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\Dto\WalletBalance;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;

/**
 * The merchant's Shipbubble wallet. The balance call doubles as the cheapest
 * way to check that an API key works.
 */
class Wallet extends ApiResource
{
    /**
     * @throws ShipbubbleApiException
     */
    public function balance(?string $salesChannelId = null): WalletBalance
    {
        return WalletBalance::fromArray($this->data($this->httpClient->get('/shipping/wallet/balance', [], $salesChannelId)));
    }
}
