<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\ReceiverAddressService;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Address\AddressLine;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(ReceiverAddressService::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Addresses::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(ValidatedAddress::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
#[UsesClass(AddressLine::class)]
#[UsesClass(QuoteError::class)]
class ReceiverAddressServiceTest extends TestCase
{
    public function testValidatesOnceAndCachesTheCode(): void
    {
        $response = ClientFactory::ok(['address_code' => 456]);
        $http = new MockHttpClient([$response]);
        $service = $this->service($http);

        static::assertSame('456', $service->addressCode(CheckoutFixture::context()));
        static::assertSame('456', $service->addressCode(CheckoutFixture::context()));
        static::assertSame(1, $http->getRequestsCount());

        $body = json_decode($response->getRequestOptions()['body'], true);
        static::assertSame('15 Awolowo Road, Lagos, Lagos, Nigeria', $body['address']);
        static::assertSame('Ada Obi', $body['name']);
        static::assertSame('ada@example.com', $body['email']);
        static::assertSame('+2348031234567', $body['phone']);
        static::assertSame('101233', $body['postal_code']);
    }

    public function testRejectedAddressIsCachedAsInvalid(): void
    {
        $http = new MockHttpClient([ClientFactory::json(['status' => 'failed', 'message' => 'Sorry'], 400)]);
        $service = $this->service($http);

        static::assertSame(QuoteError::InvalidAddress, $service->addressCode(CheckoutFixture::context()));
        static::assertSame(QuoteError::InvalidAddress, $service->addressCode(CheckoutFixture::context()));
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testUnavailableShipbubbleIsNotCached(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 503]), ClientFactory::ok(['address_code' => 7])]);
        $service = $this->service($http);

        try {
            $service->addressCode(CheckoutFixture::context());
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertTrue($e->getReason()->isTransient());
        }

        static::assertSame('7', $service->addressCode(CheckoutFixture::context()));
    }

    public function testIncompleteAddressesNeverCallShipbubble(): void
    {
        $http = new MockHttpClient([]);
        $service = $this->service($http);

        static::assertSame(QuoteError::IncompleteAddress, $service->addressCode(CheckoutFixture::context(phone: '')));
        static::assertSame(QuoteError::IncompleteAddress, $service->addressCode(CheckoutFixture::context(street: ' ')));
        static::assertSame(QuoteError::IncompleteAddress, $service->addressCode(CheckoutFixture::context(withCustomer: false)));
        static::assertSame(QuoteError::IncompleteAddress, $service->addressCode(
            Generator::generateSalesChannelContext(shippingLocation: new ShippingLocation(new CountryEntity())),
        ));
        static::assertSame(0, $http->getRequestsCount());
    }

    private function service(MockHttpClient $http): ReceiverAddressService
    {
        $config = ClientFactory::config(['mode' => 'sandbox', 'sandboxApiKey' => ClientFactory::API_KEY]);

        return new ReceiverAddressService($config, new ShipbubbleClient(new ShipbubbleHttpClient($config, $http)), new ArrayAdapter());
    }
}
