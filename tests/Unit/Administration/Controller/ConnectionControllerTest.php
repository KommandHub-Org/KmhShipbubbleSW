<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Administration\Controller;

use Kommandhub\ShipbubbleSW\Administration\Controller\ConnectionController;
use Kommandhub\ShipbubbleSW\Client\ApiErrorReason;
use Kommandhub\ShipbubbleSW\Client\Dto\ArrayReader;
use Kommandhub\ShipbubbleSW\Client\Dto\Courier;
use Kommandhub\ShipbubbleSW\Client\Dto\PackageCategory;
use Kommandhub\ShipbubbleSW\Client\Dto\ValidatedAddress;
use Kommandhub\ShipbubbleSW\Client\Dto\WalletBalance;
use Kommandhub\ShipbubbleSW\Client\Http\ShipbubbleHttpClient;
use Kommandhub\ShipbubbleSW\Client\Resource\Addresses;
use Kommandhub\ShipbubbleSW\Client\Resource\ApiResource;
use Kommandhub\ShipbubbleSW\Client\Resource\Couriers;
use Kommandhub\ShipbubbleSW\Client\Resource\Rates;
use Kommandhub\ShipbubbleSW\Client\Resource\Wallet;
use Kommandhub\ShipbubbleSW\Client\ShipbubbleClient;
use Kommandhub\ShipbubbleSW\Exception\ShipbubbleApiException;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Shipping\Sender\SenderAddressService;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ConnectionController::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleClient::class)]
#[UsesClass(ShipbubbleHttpClient::class)]
#[UsesClass(ApiResource::class)]
#[UsesClass(Wallet::class)]
#[UsesClass(Couriers::class)]
#[UsesClass(Rates::class)]
#[UsesClass(Addresses::class)]
#[UsesClass(WalletBalance::class)]
#[UsesClass(Courier::class)]
#[UsesClass(PackageCategory::class)]
#[UsesClass(ValidatedAddress::class)]
#[UsesClass(ArrayReader::class)]
#[UsesClass(SenderAddressService::class)]
#[UsesClass(ShipbubbleApiException::class)]
#[UsesClass(ApiErrorReason::class)]
class ConnectionControllerTest extends TestCase
{
    /**
     * @var list<array{string, string, array<mixed>}>
     */
    private array $logs = [];

    public function testConnectionSuccessReturnsBalance(): void
    {
        $response = ClientFactory::ok(['balance' => 2500, 'currency' => 'NGN']);

        $result = $this->decode($this->controller($response)->testConnection(new Request([], ['salesChannelId' => 'sc-1'])));

        static::assertSame(['success' => true, 'balance' => 2500, 'currency' => 'NGN'], $result);
    }

    /**
     * A failed test shows Shipbubble's reason, and neither the response nor the
     * log contains the API key.
     */
    public function testConnectionFailureReportsReasonWithoutKey(): void
    {
        $response = ClientFactory::json(['status' => 'failed', 'message' => 'Invalid API key'], 401);

        $raw = $this->controller($response)->testConnection(new Request());
        $result = $this->decode($raw);

        static::assertFalse($result['success']);
        static::assertSame('unauthorized', $result['reason']);
        static::assertSame('Invalid API key', $result['message']);
        static::assertStringNotContainsString(ClientFactory::API_KEY, (string)$raw->getContent());
        static::assertStringNotContainsString(ClientFactory::API_KEY, (string)json_encode($this->logs));
        static::assertSame('warning', $this->logs[0][0]);
        static::assertSame(401, $this->logs[0][2]['httpStatus']);
    }

    public function testUnconfiguredKey(): void
    {
        $controller = $this->controller(new MockResponse('{}'), '');

        static::assertSame('not_configured', $this->decode($controller->testConnection(new Request()))['reason']);
    }

    public function testValidateSender(): void
    {
        $response = ClientFactory::ok(['address_code' => 5, 'formatted_address' => '1 Allen Ave, Ikeja']);

        $result = $this->decode($this->controller($response, ClientFactory::API_KEY, true)->validateSender(new Request()));

        static::assertSame(['success' => true, 'formattedAddress' => '1 Allen Ave, Ikeja'], $result);
    }

    public function testValidateSenderFailure(): void
    {
        $result = $this->decode($this->controller(new MockResponse('{}'))->validateSender(new Request()));

        static::assertSame('validation', $result['reason']);
    }

    public function testCouriers(): void
    {
        $response = ClientFactory::ok([['service_code' => 'kwik', 'name' => 'Kwik']]);

        $result = $this->decode($this->controller($response)->couriers(new Request(['salesChannelId' => ''])));

        static::assertSame(['success' => true, 'options' => [['value' => 'kwik', 'label' => 'Kwik']]], $result);
    }

    public function testCouriersFailure(): void
    {
        $response = ClientFactory::json(['status' => 'failed', 'message' => 'down'], 500);

        static::assertSame('server_error', $this->decode($this->controller($response)->couriers(new Request()))['reason']);
    }

    public function testPackageCategories(): void
    {
        $response = ClientFactory::ok([['category_id' => 1, 'category' => 'Electronics']]);

        $result = $this->decode($this->controller($response)->packageCategories(new Request()));

        static::assertSame(['success' => true, 'options' => [['value' => '1', 'label' => 'Electronics']]], $result);
    }

    public function testPackageCategoriesFailure(): void
    {
        $response = ClientFactory::json(['status' => 'failed', 'message' => 'slow down'], 429);

        static::assertSame('rate_limited', $this->decode($this->controller($response)->packageCategories(new Request()))['reason']);
    }

    private function controller(MockResponse $response, string $apiKey = ClientFactory::API_KEY, bool $withSender = false): ConnectionController
    {
        $values = ['mode' => 'sandbox', 'sandboxApiKey' => $apiKey];

        if ($withSender) {
            $values += ['senderName' => 'Shop', 'senderPhone' => '1', 'senderEmail' => 'a@b.c', 'senderAddress' => 'x'];
        }

        $config = ClientFactory::config($values);
        $client = new ShipbubbleClient(new ShipbubbleHttpClient($config, new MockHttpClient($response)));
        $logs = &$this->logs;
        $logger = new class($logs) extends AbstractLogger {
            /**
             * @param list<array{string, string, array<mixed>}> $logs
             */
            public function __construct(private array &$logs)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = [(string)$level, (string)$message, $context];
            }
        };

        return new ConnectionController($client, new SenderAddressService($config, $client), $logger);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(JsonResponse $response): array
    {
        $data = json_decode((string)$response->getContent(), true);
        \assert(\is_array($data));

        return $data;
    }
}
