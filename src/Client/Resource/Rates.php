<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageBox;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageCategory;
use Kommandhub\ShipbubbleSW\Client\Dto\RateQuote;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;

/**
 * Rate quotes and the reference data a rate request needs.
 */
class Rates extends ApiResource
{
    /**
     * The payload follows the Shipbubble field names (`sender_address_code`,
     * `reciever_address_code` — sic, `package_items`, `package_dimension`, …);
     * it is assembled by the quote service (spec §6).
     *
     * @param array<string, mixed> $payload
     * @param list<string> $serviceCodes restrict the quote to these couriers; empty = all
     *
     * @throws ShipbubbleApiException
     */
    public function fetch(array $payload, array $serviceCodes = [], ?string $salesChannelId = null): RateQuote
    {
        $endpoint = '/shipping/fetch_rates';

        if ($serviceCodes !== []) {
            $endpoint .= '/' . rawurlencode(implode(',', $serviceCodes));
        }

        return RateQuote::fromArray($this->data($this->httpClient->post($endpoint, $payload, $salesChannelId)));
    }

    /**
     * @return list<PackageCategory>
     *
     * @throws ShipbubbleApiException
     */
    public function categories(?string $salesChannelId = null): array
    {
        $data = $this->data($this->httpClient->get('/shipping/labels/categories', [], $salesChannelId));

        return array_map(PackageCategory::fromArray(...), ArrayReader::rows($data));
    }

    /**
     * @return list<PackageBox>
     *
     * @throws ShipbubbleApiException
     */
    public function boxes(?string $salesChannelId = null): array
    {
        $data = $this->data($this->httpClient->get('/shipping/labels/boxes', [], $salesChannelId));

        return array_map(PackageBox::fromArray(...), ArrayReader::rows($data));
    }
}
