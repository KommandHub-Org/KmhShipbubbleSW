<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;

/**
 * Shipments, which Shipbubble calls labels. Creating one charges the
 * merchant's wallet.
 */
class Labels extends ApiResource
{
    /**
     * @throws ShipbubbleApiException reason InsufficientBalance when the wallet is empty
     */
    public function create(string $requestToken, string $serviceCode, string $courierId, ?string $salesChannelId = null): Shipment
    {
        $payload = [
            'request_token' => $requestToken,
            'service_code' => $serviceCode,
            'courier_id' => $courierId,
        ];

        return Shipment::fromArray($this->data($this->httpClient->post('/shipping/labels', $payload, $salesChannelId)));
    }

    /**
     * @param list<string> $orderIds Shipbubble order ids, at most 100 per call
     *
     * @return list<Shipment>
     *
     * @throws ShipbubbleApiException
     */
    public function get(array $orderIds, ?string $salesChannelId = null): array
    {
        if ($orderIds === []) {
            return [];
        }

        $data = $this->data($this->httpClient->get('/shipping/labels/list/' . rawurlencode(implode(',', $orderIds)), [], $salesChannelId));

        // Paginated responses wrap the rows in `results`; plain lists do not.
        $rows = isset($data['results']) ? ArrayReader::array($data, 'results') : $data;

        return array_map(Shipment::fromArray(...), ArrayReader::rows($rows));
    }

    /**
     * @throws ShipbubbleApiException with Shipbubble's reason when the shipment can no longer be cancelled
     */
    public function cancel(string $orderId, ?string $salesChannelId = null): void
    {
        $this->data($this->httpClient->post('/shipping/labels/cancel/' . rawurlencode($orderId), [], $salesChannelId));
    }
}
