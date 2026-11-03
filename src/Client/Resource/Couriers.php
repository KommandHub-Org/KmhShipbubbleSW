<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Courier;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;

/**
 * The couriers available to the merchant's account.
 */
class Couriers extends ApiResource
{
    /**
     * @return list<Courier>
     *
     * @throws ShipbubbleApiException
     */
    public function list(?string $salesChannelId = null): array
    {
        $data = $this->data($this->httpClient->get('/shipping/couriers', [], $salesChannelId));

        return array_map(Courier::fromArray(...), ArrayReader::rows($data));
    }
}
