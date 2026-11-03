<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Quote;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageBox;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\Resource\Rates;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Quote\Package;
use Kommandhub\ShipbubbleSW\Quote\PackageBuilder;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(PackageBuilder::class)]
#[CoversClass(Package::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Rates::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(PackageBox::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
class PackageBuilderTest extends TestCase
{
    private const BOXES = [
        ['name' => 'Envelope', 'length' => 25, 'width' => 35, 'height' => 2, 'max_weight' => 0.5],
        ['name' => 'Box', 'length' => 30, 'width' => 30, 'height' => 20, 'max_weight' => 5],
    ];

    public function testItemsAndTheFirstBoxThatCarriesTheWeight(): void
    {
        $package = $this->builder(ClientFactory::ok(self::BOXES))->build(CheckoutFixture::cart(['p1' => 1.5]), null);

        static::assertSame([[
            'name' => 'Product p1',
            'description' => 'SW-p1',
            'unit_weight' => '1.5',
            'unit_amount' => '5000',
            'quantity' => '2',
        ]], $package->items);
        static::assertSame(['length' => 30.0, 'width' => 30.0, 'height' => 20.0], $package->dimension);
        static::assertSame('20754594', $package->categoryId);
    }

    public function testOrderParcelUsesTheProductWeight(): void
    {
        $builder = $this->builder(ClientFactory::ok(self::BOXES));

        $package = $builder->buildForOrder(CheckoutFixture::order(1.5));
        static::assertSame([[
            'name' => 'Product p1',
            'description' => 'SW-p1',
            'unit_weight' => '1.5',
            'unit_amount' => '5000',
            'quantity' => '2',
        ]], $package->items);

        static::assertSame('0.25', $builder->buildForOrder(CheckoutFixture::order(null))->items[0]['unit_weight']);
    }

    public function testMissingWeightUsesTheDefaultAndIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('notice');

        $package = $this->builder(ClientFactory::ok(self::BOXES), $logger)->build(CheckoutFixture::cart(['p1' => null]), null);

        static::assertSame('0.25', $package->items[0]['unit_weight']);
        // 2 × 0.25 kg fits the 0.5 kg envelope.
        static::assertSame(['length' => 25.0, 'width' => 35.0, 'height' => 2.0], $package->dimension);
    }

    public function testDefaultBoxWhenNothingFitsOrBoxesCannotBeLoaded(): void
    {
        $default = ['length' => 56.0, 'width' => 50.0, 'height' => 45.0];

        static::assertSame($default, $this->builder(ClientFactory::ok(self::BOXES))->build(CheckoutFixture::cart(['p1' => 10.0]), null)->dimension);
        static::assertSame($default, $this->builder(new MockResponse('', ['http_code' => 500]))->build(CheckoutFixture::cart(), null)->dimension);
    }

    public function testBoxesAreCached(): void
    {
        $http = new MockHttpClient([ClientFactory::ok(self::BOXES)]);
        $config = $this->config();
        $builder = new PackageBuilder($config, new ShipbubbleClient(new ShipbubbleHttpClient($config, $http)), new ArrayAdapter(), new NullLogger());

        $builder->build(CheckoutFixture::cart(), null);
        $builder->build(CheckoutFixture::cart(), null);

        static::assertSame(1, $http->getRequestsCount());
    }

    public function testSignatureIgnoresLineItemOrderButNotQuantities(): void
    {
        $builder = $this->builder(ClientFactory::ok(self::BOXES));
        $ab = $builder->build(CheckoutFixture::cart(['a' => 1.0, 'b' => 2.0]), null);
        $ba = $builder->build(CheckoutFixture::cart(['b' => 2.0, 'a' => 1.0]), null);
        $more = $builder->build(CheckoutFixture::cart(['a' => 1.0, 'b' => 2.0], 3), null);

        static::assertSame($ab->signature(), $ba->signature());
        static::assertNotSame($ab->signature(), $more->signature());
    }

    private function config(): Config
    {
        return ClientFactory::config([
            'mode' => 'sandbox',
            'sandboxApiKey' => ClientFactory::API_KEY,
            'defaultProductWeight' => 0.25,
            'packageCategoryId' => '20754594',
        ]);
    }

    private function builder(MockResponse $boxes, ?LoggerInterface $logger = null): PackageBuilder
    {
        $config = $this->config();

        return new PackageBuilder($config, new ShipbubbleClient(new ShipbubbleHttpClient($config, new MockHttpClient($boxes))), new ArrayAdapter(), $logger ?? new NullLogger());
    }
}
