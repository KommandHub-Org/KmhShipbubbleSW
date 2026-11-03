<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Client\Http;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ShipbubbleHttpClient::class)]
#[UsesClass(Config::class)]
#[CoversClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
class ShipbubbleHttpClientTest extends TestCase
{
    public function testGetSendsBearerKeyPlatformHeaderAndShortTimeout(): void
    {
        $response = new MockResponse('{}');
        $http = ClientFactory::http($response);

        $http->get('/shipping/wallet/balance', ['a' => 'b'], 'sc-1');

        static::assertSame('GET', $response->getRequestMethod());
        static::assertSame('https://api.shipbubble.com/v1/shipping/wallet/balance?a=b', $response->getRequestUrl());
        $headers = $response->getRequestOptions()['headers'];
        static::assertContains('Authorization: Bearer ' . ClientFactory::API_KEY, $headers);
        static::assertContains('x-shipbubble-platform: shopware', $headers);
        static::assertSame(3.0, (float)$response->getRequestOptions()['timeout']);
        static::assertSame(5.0, (float)$response->getRequestOptions()['max_duration']);
    }

    public function testPostSendsJsonBody(): void
    {
        $response = new MockResponse('{}');

        ClientFactory::http($response)->post('/shipping/labels', ['request_token' => 't']);

        static::assertSame('POST', $response->getRequestMethod());
        static::assertSame('{"request_token":"t"}', $response->getRequestOptions()['body']);
    }

    public function testLiveModeUsesLiveKey(): void
    {
        $response = new MockResponse('{}');
        $config = ClientFactory::config(['mode' => 'live', 'sandboxApiKey' => 'sb_sandbox_x', 'liveApiKey' => 'sb_prod_y']);

        (new ShipbubbleHttpClient($config, new MockHttpClient($response)))->get('/x');

        static::assertContains('Authorization: Bearer sb_prod_y', $response->getRequestOptions()['headers']);
    }

    public function testMissingKeyFailsBeforeAnyRequest(): void
    {
        $client = new MockHttpClient(static fn () => throw new \LogicException('must not be called'));
        $config = ClientFactory::config(['mode' => 'sandbox']);

        try {
            (new ShipbubbleHttpClient($config, $client))->get('/x');
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertSame(ApiErrorReason::NotConfigured, $e->getReason());
            static::assertSame(0, $client->getRequestsCount());
        }
    }
}
