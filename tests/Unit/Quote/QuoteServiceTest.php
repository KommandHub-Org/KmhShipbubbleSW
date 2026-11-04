<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\CourierRate;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageBox;
use Kommandhub\ShipbubbleSW\Client\Dto\RateQuote;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\Resource\Rates;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Quote\CourierOption;
use Kommandhub\ShipbubbleSW\Quote\FailureNotifier;
use Kommandhub\ShipbubbleSW\Quote\Package;
use Kommandhub\ShipbubbleSW\Quote\PackageBuilder;
use Kommandhub\ShipbubbleSW\Quote\QuoteError;
use Kommandhub\ShipbubbleSW\Quote\QuoteService;
use Kommandhub\ShipbubbleSW\Quote\QuoteStruct;
use Kommandhub\ShipbubbleSW\Quote\ReceiverAddressService;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Address\AddressLine;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Notification\NotificationService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(QuoteService::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Addresses::class)]
#[UsesClass(Rates::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(ValidatedAddress::class)]
#[UsesClass(RateQuote::class)]
#[UsesClass(CourierRate::class)]
#[UsesClass(PackageBox::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
#[UsesClass(AddressLine::class)]
#[UsesClass(QuoteError::class)]
#[UsesClass(QuoteStruct::class)]
#[UsesClass(CourierOption::class)]
#[UsesClass(Package::class)]
#[UsesClass(PackageBuilder::class)]
#[UsesClass(ReceiverAddressService::class)]
#[UsesClass(FailureNotifier::class)]
class QuoteServiceTest extends TestCase
{
    private const COURIERS = [
        ['courier_id' => 'c2', 'courier_name' => 'Two', 'service_code' => 's2', 'total' => 800, 'currency' => 'NGN', 'delivery_eta' => '2 days'],
        ['courier_id' => 'c1', 'courier_name' => 'One', 'service_code' => 's1', 'total' => 500, 'currency' => 'NGN', 'delivery_eta' => '1 day'],
    ];

    private int $rateCalls = 0;

    /**
     * @var array<mixed>|null
     */
    private ?array $rateBody = null;

    private string $rateUrl = '';

    public function testQuotesCheapestFirstWithMarkupAndToken(): void
    {
        $quote = $this->service(['markupType' => 'fixed', 'markupValue' => 100])->quote(CheckoutFixture::cart(), CheckoutFixture::context());

        static::assertInstanceOf(QuoteStruct::class, $quote);
        static::assertSame(['c1', 'c2'], array_map(static fn (CourierOption $c): string => $c->courierId, $quote->couriers));
        static::assertSame(500.0, $quote->couriers[0]->carrierTotal);
        static::assertSame(600.0, $quote->couriers[0]->price);
        static::assertSame('tok', $quote->requestToken);
        static::assertSame('NGN', $quote->currency);
        static::assertSame('sandbox', $quote->mode);
        static::assertNull($quote->selected);

        static::assertSame(123, $this->rateBody['sender_address_code']);
        static::assertSame(456, $this->rateBody['reciever_address_code']);
        static::assertSame(20754594, $this->rateBody['category_id']);
        static::assertNotSame('', $this->rateBody['delivery_instructions']);
        static::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $this->rateBody['pickup_date']);
        static::assertStringEndsWith('/shipping/fetch_rates', $this->rateUrl);
    }

    public function testPercentMarkupAndRestrictedCouriers(): void
    {
        $quote = $this->service(['markupType' => 'percent', 'markupValue' => 10, 'enabledCouriers' => ['s2', 's1']])
            ->quote(CheckoutFixture::cart(), CheckoutFixture::context());

        static::assertInstanceOf(QuoteStruct::class, $quote);
        static::assertEqualsWithDelta(550.0, $quote->couriers[0]->price, 0.001);
        static::assertStringEndsWith('/shipping/fetch_rates/s1%2Cs2', $this->rateUrl);
    }

    public function testReloadUsesTheCacheAndAQuantityChangeReQuotes(): void
    {
        $service = $this->service();

        $service->quote(CheckoutFixture::cart(), CheckoutFixture::context());
        $service->quote(CheckoutFixture::cart(), CheckoutFixture::context());
        static::assertSame(1, $this->rateCalls);

        $service->quote(CheckoutFixture::cart(quantity: 3), CheckoutFixture::context());
        static::assertSame(2, $this->rateCalls);
    }

    public function testNoCouriersAndCurrencyMismatch(): void
    {
        static::assertSame(QuoteError::NoCouriers, $this->service(couriers: [])->quote(CheckoutFixture::cart(), CheckoutFixture::context()));
        static::assertSame(QuoteError::CurrencyMismatch, $this->service()->quote(CheckoutFixture::cart(), CheckoutFixture::context(currency: 'EUR')));
    }

    public function testMissingSenderCodeIsNotConfiguredAndNotifies(): void
    {
        $notifications = $this->createMock(NotificationService::class);
        $notifications->expects(static::once())->method('createNotification');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('log')->with('error', static::anything(), static::callback(
            static fn (array $context): bool => $context['reason'] === 'not_configured' && !str_contains((string)json_encode($context), ClientFactory::API_KEY),
        ));

        $result = $this->service(sender: '', notifications: $notifications, logger: $logger)->quote(CheckoutFixture::cart(), CheckoutFixture::context());

        static::assertSame(QuoteError::NotConfigured, $result);
        static::assertSame(0, $this->rateCalls);
    }

    public function testRefusalLogsShipbubblesMessage(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('log')->with('error', static::anything(), static::callback(
            static fn (array $context): bool => $context['httpStatus'] === 400 && $context['message'] === 'No courier available',
        ));

        $this->service(rates: static fn (): MockResponse => ClientFactory::json(['status' => 'failed', 'message' => 'No courier available'], 400), logger: $logger)
            ->quote(CheckoutFixture::cart(), CheckoutFixture::context());
    }

    public function testIncompleteAddressIsLoggedAsNotice(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('log')->with('notice');

        static::assertSame(QuoteError::IncompleteAddress, $this->service(logger: $logger)->quote(CheckoutFixture::cart(), CheckoutFixture::context(phone: '')));
    }

    public function testRequoteOfAnOrderNeverUsesTheCache(): void
    {
        $service = $this->service();
        $service->quote(CheckoutFixture::cart(), CheckoutFixture::context());

        $first = $service->requote(CheckoutFixture::order(), CheckoutFixture::orderAddress(), Context::createDefaultContext());
        $service->requote(CheckoutFixture::order(), CheckoutFixture::orderAddress(), Context::createDefaultContext());

        static::assertInstanceOf(QuoteStruct::class, $first);
        static::assertSame('tok', $first->requestToken);
        static::assertSame(3, $this->rateCalls);
    }

    public function testRequoteWithAnIncompleteOrderAddress(): void
    {
        static::assertSame(
            QuoteError::IncompleteAddress,
            $this->service()->requote(CheckoutFixture::order(), CheckoutFixture::orderAddress(phone: ''), Context::createDefaultContext()),
        );
    }

    public function testTimeoutIsUnavailableAndNotCached(): void
    {
        $service = $this->service(rates: static fn (): MockResponse => new MockResponse('', ['http_code' => 504]));

        static::assertSame(QuoteError::Unavailable, $service->quote(CheckoutFixture::cart(), CheckoutFixture::context()));
        static::assertSame(QuoteError::Unavailable, $service->quote(CheckoutFixture::cart(), CheckoutFixture::context()));
        static::assertSame(2, $this->rateCalls);
    }

    public function testUnreachableShipbubbleIsNotCalledAgainForAMinute(): void
    {
        $service = $this->service(rates: static fn (): MockResponse => new MockResponse('', ['error' => 'Could not resolve host']));

        static::assertSame(QuoteError::Unavailable, $service->quote(CheckoutFixture::cart(), CheckoutFixture::context()));
        static::assertSame(QuoteError::Unavailable, $service->quote(CheckoutFixture::cart(), CheckoutFixture::context()));
        static::assertSame(1, $this->rateCalls);
    }

    /**
     * @param array<string, mixed> $config
     * @param list<array<string, mixed>> $couriers
     */
    private function service(
        array $config = [],
        array $couriers = self::COURIERS,
        string $sender = '123',
        ?\Closure $rates = null,
        ?NotificationService $notifications = null,
        ?LoggerInterface $logger = null,
    ): QuoteService {
        $config = ClientFactory::config(array_merge([
            'mode' => 'sandbox',
            'sandboxApiKey' => ClientFactory::API_KEY,
            'packageCategoryId' => '20754594',
        ], $config));

        if ($sender !== '') {
            $config->setSenderAddressCode($sender);
        }

        $rates ??= static fn (): MockResponse => ClientFactory::ok(['request_token' => 'tok', 'couriers' => $couriers]);
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($rates): ResponseInterface {
            if (str_contains($url, '/address/validate')) {
                return ClientFactory::ok(['address_code' => 456]);
            }

            if (str_contains($url, '/labels/boxes')) {
                return ClientFactory::ok([]);
            }
            ++$this->rateCalls;
            $this->rateUrl = $url;
            $this->rateBody = json_decode($options['body'], true);

            return $rates();
        });
        $client = new ShipbubbleClient(new ShipbubbleHttpClient($config, $http));
        $cache = new ArrayAdapter();

        return new QuoteService(
            $config,
            $client,
            new ReceiverAddressService($config, $client, $cache),
            new PackageBuilder($config, $client, $cache, new NullLogger()),
            new FailureNotifier($notifications ?? $this->createMock(NotificationService::class), $cache),
            $cache,
            $logger ?? new NullLogger(),
        );
    }
}
