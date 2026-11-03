<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Client\Resource;

use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Courier;
use Kommandhub\ShipbubbleSW\Client\Dto\CourierRate;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageBox;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageCategory;
use Kommandhub\ShipbubbleSW\Client\Dto\RateQuote;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\Resource\Couriers;
use Kommandhub\ShipbubbleSW\Client\Resource\Labels;
use Kommandhub\ShipbubbleSW\Client\Resource\Rates;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

#[CoversClass(Addresses::class)]
#[CoversClass(Rates::class)]
#[CoversClass(Couriers::class)]
#[CoversClass(Labels::class)]
#[CoversClass(ShipbubbleClient::class)]
#[CoversClass(ValidatedAddress::class)]
#[CoversClass(RateQuote::class)]
#[CoversClass(CourierRate::class)]
#[CoversClass(Courier::class)]
#[CoversClass(PackageCategory::class)]
#[CoversClass(PackageBox::class)]
#[CoversClass(Shipment::class)]
#[CoversClass(ArrayReader::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
class ResourcesTest extends TestCase
{
    public function testValidateAddress(): void
    {
        $response = ClientFactory::ok([
            'address_code' => 12345,
            'formatted_address' => '1 Allen Ave, Ikeja, Lagos, Nigeria',
            'city' => 'Ikeja',
            'state' => 'Lagos',
            'country_code' => 'NG',
        ]);

        $address = ClientFactory::client($response)->addresses()->validate('Ada', 'ada@example.com', '+2348000000000', '1 Allen Ave', '100271');

        static::assertSame('/v1/shipping/address/validate', parse_url($response->getRequestUrl(), \PHP_URL_PATH));
        static::assertSame(
            ['name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '+2348000000000', 'address' => '1 Allen Ave', 'postal_code' => '100271'],
            json_decode($response->getRequestOptions()['body'], true),
        );
        static::assertSame('12345', $address->addressCode);
        static::assertSame('1 Allen Ave, Ikeja, Lagos, Nigeria', $address->formattedAddress);
        static::assertSame('Ikeja', $address->city);
        static::assertSame('Lagos', $address->state);
        static::assertSame('NG', $address->countryCode);
    }

    public function testValidateAddressOmitsEmptyPostalCode(): void
    {
        $response = ClientFactory::ok(['address_code' => 1]);

        ClientFactory::client($response)->addresses()->validate('Ada', 'a@b.c', '1', 'x', '');

        static::assertArrayNotHasKey('postal_code', json_decode($response->getRequestOptions()['body'], true));
    }

    public function testFetchRates(): void
    {
        $response = ClientFactory::ok([
            'request_token' => 'tok_1',
            'couriers' => [
                [
                    'courier_id' => 'gig',
                    'courier_name' => 'GIG Logistics',
                    'service_code' => 'gig_std',
                    'total' => '3500.00',
                    'currency' => 'NGN',
                    'delivery_eta' => 'Within 2 days',
                    'courier_image' => 'https://img/gig.png',
                    'is_cod_available' => true,
                ],
                'not-a-courier',
            ],
        ]);

        $quote = ClientFactory::client($response)->rates()->fetch(['category_id' => 1]);

        static::assertSame('/v1/shipping/fetch_rates', parse_url($response->getRequestUrl(), \PHP_URL_PATH));
        static::assertSame('tok_1', $quote->requestToken);
        static::assertCount(1, $quote->couriers);
        $courier = $quote->couriers[0];
        static::assertSame('gig', $courier->courierId);
        static::assertSame('GIG Logistics', $courier->courierName);
        static::assertSame('gig_std', $courier->serviceCode);
        static::assertSame(3500.0, $courier->total);
        static::assertSame('NGN', $courier->currency);
        static::assertSame('Within 2 days', $courier->deliveryEta);
        static::assertSame('https://img/gig.png', $courier->courierImage);
        static::assertTrue($courier->codAvailable);
    }

    public function testFetchRatesForSelectedCouriers(): void
    {
        $response = ClientFactory::ok(['request_token' => 't', 'couriers' => []]);

        ClientFactory::client($response)->rates()->fetch([], ['gig_std', 'kwik']);

        static::assertSame('https://api.shipbubble.com/v1/shipping/fetch_rates/gig_std%2Ckwik', $response->getRequestUrl());
    }

    public function testCategoriesAndBoxes(): void
    {
        $client = ClientFactory::client([
            ClientFactory::ok([['category_id' => 74794423, 'category' => 'Hot food'], 'x']),
            ClientFactory::ok([['name' => 'Small Box', 'length' => 30, 'width' => '20', 'height' => 10, 'max_weight' => 5]]),
        ]);

        $categories = $client->rates()->categories();
        $boxes = $client->rates()->boxes();

        static::assertCount(1, $categories);
        static::assertSame('74794423', $categories[0]->id);
        static::assertSame('Hot food', $categories[0]->name);
        static::assertSame('Small Box', $boxes[0]->name);
        static::assertSame(30.0, $boxes[0]->length);
        static::assertSame(20.0, $boxes[0]->width);
        static::assertSame(10.0, $boxes[0]->height);
        static::assertSame(5.0, $boxes[0]->maxWeight);
    }

    public function testCouriersFallBackToServiceCodeAsName(): void
    {
        $couriers = ClientFactory::client(ClientFactory::ok([
            ['service_code' => 'kwik', 'name' => 'Kwik'],
            ['service_code' => 'dhl'],
        ]))->couriers()->list();

        static::assertSame('Kwik', $couriers[0]->name);
        static::assertSame('dhl', $couriers[1]->name);
    }

    public function testCreateLabel(): void
    {
        $response = ClientFactory::ok([
            'order_id' => 'SB-1',
            'status' => 'pending',
            'courier' => ['name' => 'GIG Logistics'],
            'tracking_url' => 'https://track/SB-1',
        ]);

        $shipment = ClientFactory::client($response)->labels()->create('tok', 'gig_std', 'gig');

        static::assertSame(['request_token' => 'tok', 'service_code' => 'gig_std', 'courier_id' => 'gig'], json_decode($response->getRequestOptions()['body'], true));
        static::assertSame('SB-1', $shipment->orderId);
        static::assertSame('pending', $shipment->status);
        static::assertSame('GIG Logistics', $shipment->courierName);
        static::assertSame('https://track/SB-1', $shipment->trackingUrl);
        static::assertNull($shipment->waybillUrl);
    }

    public function testGetLabelsHandlesPaginatedAndPlainLists(): void
    {
        $paginated = ClientFactory::ok(['results' => [['order_id' => 'SB-1', 'courier' => 'Kwik', 'waybill_document' => 'https://w/1.pdf']]]);
        $plain = ClientFactory::ok([['order_id' => 'SB-2']]);
        $client = ClientFactory::client([$paginated, $plain]);

        $first = $client->labels()->get(['SB-1', 'SB-3']);
        $second = $client->labels()->get(['SB-2']);

        static::assertSame('https://api.shipbubble.com/v1/shipping/labels/list/SB-1%2CSB-3', $paginated->getRequestUrl());
        static::assertSame('Kwik', $first[0]->courierName);
        static::assertSame('https://w/1.pdf', $first[0]->waybillUrl);
        static::assertSame('SB-2', $second[0]->orderId);
    }

    public function testGetLabelsWithoutIdsMakesNoRequest(): void
    {
        $http = new MockHttpClient([]);
        $client = new ShipbubbleClient(new ShipbubbleHttpClient(ClientFactory::config(['sandboxApiKey' => 'k']), $http));

        static::assertSame([], $client->labels()->get([]));
        static::assertSame(0, $http->getRequestsCount());
    }

    public function testCancelLabel(): void
    {
        $response = ClientFactory::ok([]);

        ClientFactory::client($response)->labels()->cancel('SB 1');

        static::assertSame('POST', $response->getRequestMethod());
        static::assertSame('https://api.shipbubble.com/v1/shipping/labels/cancel/SB%201', $response->getRequestUrl());
    }

    public function testArrayReaderTolerance(): void
    {
        static::assertSame('', ArrayReader::string(['a' => ['x']], 'a'));
        static::assertSame('1.5', ArrayReader::string(['a' => 1.5], 'a'));
        static::assertNull(ArrayReader::nullableString([], 'a'));
        static::assertSame(0.0, ArrayReader::float(['a' => 'abc'], 'a'));
        static::assertFalse(ArrayReader::bool(['a' => 'no'], 'a'));
        static::assertTrue(ArrayReader::bool(['a' => '1'], 'a'));
        static::assertSame([], ArrayReader::array(['a' => 'x'], 'a'));
    }
}
