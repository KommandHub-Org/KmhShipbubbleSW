<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Http;

use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport for the Shipbubble API. Shipbubble only uses GET and POST for the
 * calls this plugin makes.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $queryParams
     *
     * @throws ShipbubbleApiException when no API key is configured
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ShipbubbleApiException when no API key is configured
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;
}
