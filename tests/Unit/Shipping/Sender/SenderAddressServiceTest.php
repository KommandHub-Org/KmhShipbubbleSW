<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping\Sender;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Sender\SenderAddressService;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(SenderAddressService::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Addresses::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(ValidatedAddress::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
class SenderAddressServiceTest extends TestCase
{
    private const SENDER = [
        'mode' => 'sandbox',
        'sandboxApiKey' => 'sb_sandbox_k',
        'senderName' => 'Shop Ltd',
        'senderPhone' => '+2348000000000',
        'senderEmail' => 'shop@example.com',
        'senderAddress' => '1 Allen Ave',
        'senderCity' => 'Ikeja',
        'senderState' => 'Lagos',
    ];

    public function testValidatesAndStoresCode(): void
    {
        $response = ClientFactory::ok(['address_code' => 777, 'formatted_address' => '1 Allen Ave, Ikeja']);
        $config = ClientFactory::config(self::SENDER);

        $address = $this->service($config, $response)->validateAndStore();

        static::assertSame('1 Allen Ave, Ikeja, Lagos, NG', json_decode($response->getRequestOptions()['body'], true)['address']);
        static::assertSame('1 Allen Ave, Ikeja', $address->formattedAddress);
        static::assertSame('777', $config->getSenderAddressCode());
    }

    public function testIncompleteSenderIsRejectedWithoutApiCall(): void
    {
        $http = new MockHttpClient([]);
        $config = ClientFactory::config(array_merge(self::SENDER, ['senderEmail' => '']));
        $service = new SenderAddressService($config, new ShipbubbleClient(new ShipbubbleHttpClient($config, $http)));

        try {
            $service->validateAndStore();
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertSame(ApiErrorReason::Validation, $e->getReason());
            static::assertSame(0, $http->getRequestsCount());
        }
    }

    public function testMissingAddressCodeIsAnError(): void
    {
        $config = ClientFactory::config(self::SENDER);

        try {
            $this->service($config, ClientFactory::ok(['formatted_address' => 'x']))->validateAndStore();
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertSame(ApiErrorReason::InvalidResponse, $e->getReason());
            static::assertSame('', $config->getSenderAddressCode());
        }
    }

    private function service(Config $config, MockResponse $response): SenderAddressService
    {
        return new SenderAddressService($config, new ShipbubbleClient(new ShipbubbleHttpClient($config, new MockHttpClient($response))));
    }
}
