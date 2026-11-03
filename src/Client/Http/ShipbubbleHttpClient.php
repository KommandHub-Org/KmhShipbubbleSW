<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Client\Http;

use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Transport for the Shipbubble REST API.
 *
 * The only class in the plugin that knows the base URL and how the API key is
 * presented. Sandbox and live share one host; the key decides the mode.
 *
 * Credentials are resolved per request rather than per instance: they are
 * sales-channel scoped settings, and this service is shared across sales
 * channels within one request.
 *
 * Symfony responses are lazy: transport faults (timeouts, DNS, TLS) surface
 * when the response is read, which happens in ApiResource::response().
 */
class ShipbubbleHttpClient implements HttpClientInterface
{
    private const BASE_URL = 'https://api.shipbubble.com/v1';

    /**
     * Checkout waits on these calls, so they must give up quickly (spec §1).
     */
    private const TIMEOUT = 3;

    private const MAX_DURATION = 5;

    public function __construct(
        private readonly Config $config,
        private readonly SymfonyHttpClientInterface $client,
    ) {
    }

    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('GET', $endpoint, ['query' => $queryParams], $salesChannelId);
    }

    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface
    {
        return $this->request('POST', $endpoint, ['json' => $payload], $salesChannelId);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws ShipbubbleApiException
     */
    private function request(string $method, string $endpoint, array $options, ?string $salesChannelId): ResponseInterface
    {
        $apiKey = $this->config->getApiKey($salesChannelId);

        if ($apiKey === '') {
            throw ShipbubbleApiException::notConfigured();
        }

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $apiKey,
            'Accept' => 'application/json',
            'x-shipbubble-platform' => 'shopware',
        ];
        $options['timeout'] = self::TIMEOUT;
        $options['max_duration'] = self::MAX_DURATION;

        return $this->client->request($method, self::BASE_URL . $endpoint, $options);
    }
}
