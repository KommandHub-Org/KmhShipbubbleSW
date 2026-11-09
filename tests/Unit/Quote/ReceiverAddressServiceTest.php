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
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Doctrine\DBAL\Connection;
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
    private AddressTable $table;

    private ArrayAdapter $cache;

    protected function setUp(): void
    {
        $this->table = new AddressTable();
        $this->cache = new ArrayAdapter();
    }

    public function testStoredCodeOutlivesTheCacheAndIsNeverValidatedAgain(): void
    {
        $http = new MockHttpClient([ClientFactory::ok(['address_code' => 456])]);
        $service = $this->service($http);

        static::assertSame('456', $this->code($service, CheckoutFixture::context()));
        static::assertCount(1, $this->table->rows);

        $this->cache->clear();
        static::assertSame('456', $this->code($this->service($http), CheckoutFixture::context()));
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testSpacingAndCaseDoNotMakeANewAddress(): void
    {
        $http = new MockHttpClient([ClientFactory::ok(['address_code' => 456])]);
        $service = $this->service($http);

        $this->code($service, CheckoutFixture::context());
        static::assertSame('456', $this->code($service, CheckoutFixture::context(street: ' 15  awolowo ROAD ')));
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testSandboxAndLiveCodesAreKeptApart(): void
    {
        $http = new MockHttpClient([ClientFactory::ok(['address_code' => 1]), ClientFactory::ok(['address_code' => 2])]);

        static::assertSame('1', $this->code($this->service($http), CheckoutFixture::context()));
        static::assertSame('2', $this->code($this->service($http, 'live'), CheckoutFixture::context()));
        static::assertCount(2, $this->table->rows);
    }

    public function testForgetMakesTheNextQuoteValidateAgain(): void
    {
        $http = new MockHttpClient([ClientFactory::ok(['address_code' => 456]), ClientFactory::ok(['address_code' => 789])]);
        $service = $this->service($http);
        $context = CheckoutFixture::context();

        $this->code($service, $context);
        $address = $context->getShippingLocation()->getAddress();
        static::assertInstanceOf(CustomerAddressEntity::class, $address);
        $service->forget($address, 'ada@example.com', $context->getSalesChannelId());

        static::assertSame([], $this->table->rows);
        static::assertSame('789', $this->code($service, $context));
    }

    public function testValidatesOnceAndCachesTheCode(): void
    {
        $response = ClientFactory::ok(['address_code' => 456]);
        $http = new MockHttpClient([$response]);
        $service = $this->service($http);

        static::assertSame('456', $this->code($service, CheckoutFixture::context()));
        static::assertSame('456', $this->code($service, CheckoutFixture::context()));
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

        static::assertSame(QuoteError::InvalidAddress, $this->code($service, CheckoutFixture::context()));
        static::assertSame(QuoteError::InvalidAddress, $this->code($service, CheckoutFixture::context()));
        static::assertSame(1, $http->getRequestsCount());
    }

    public function testUnavailableShipbubbleIsNotCached(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['http_code' => 503]), ClientFactory::ok(['address_code' => 7])]);
        $service = $this->service($http);

        try {
            $this->code($service, CheckoutFixture::context());
            static::fail('Expected exception');
        } catch (ShipbubbleApiException $e) {
            static::assertTrue($e->getReason()->isTransient());
        }

        static::assertSame('7', $this->code($service, CheckoutFixture::context()));
    }

    public function testIncompleteAddressesNeverCallShipbubble(): void
    {
        $http = new MockHttpClient([]);
        $service = $this->service($http);

        static::assertSame(QuoteError::IncompleteAddress, $this->code($service, CheckoutFixture::context(phone: '')));
        static::assertSame(QuoteError::IncompleteAddress, $this->code($service, CheckoutFixture::context(street: ' ')));
        static::assertSame(QuoteError::IncompleteAddress, $this->code($service, CheckoutFixture::context(withCustomer: false)));
        static::assertSame(0, $http->getRequestsCount());
    }

    private function service(MockHttpClient $http, string $mode = 'sandbox'): ReceiverAddressService
    {
        $config = ClientFactory::config(['mode' => $mode, $mode . 'ApiKey' => ClientFactory::API_KEY]);

        return new ReceiverAddressService(
            $config,
            new ShipbubbleClient(new ShipbubbleHttpClient($config, $http)),
            $this->cache,
            $this->table->connect($this->createStub(Connection::class)),
        );
    }

    private function code(ReceiverAddressService $service, SalesChannelContext $context): string|QuoteError
    {
        $address = $context->getShippingLocation()->getAddress();
        static::assertInstanceOf(CustomerAddressEntity::class, $address);

        return $service->addressCodeFor($address, (string)$context->getCustomer()?->getEmail(), $context->getSalesChannelId());
    }
}
