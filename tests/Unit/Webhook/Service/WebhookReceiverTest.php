<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Webhook\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use Kommandhub\ShipbubbleSW\Webhook\Message\ShipbubbleWebhookMessage;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookReceiver;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(WebhookReceiver::class)]
#[UsesClass(WebhookSignatureValidator::class)]
#[UsesClass(Config::class)]
#[UsesClass(ShipbubbleWebhookMessage::class)]
class WebhookReceiverTest extends TestCase
{
    private const KEY = 'sb_sandbox_k';

    public function testStoresAndQueuesAValidWebhookOnce(): void
    {
        $body = '{"event":"shipment.status.changed","order_id":"SB-1"}';
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(2))->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params): int {
                static::assertStringContainsString('INSERT IGNORE', $sql);
                static::assertSame(hash('sha256', '{"event":"shipment.status.changed","order_id":"SB-1"}'), $params['key']);
                static::assertSame('shipment.status.changed', $params['event']);
                static::assertSame('SB-1', $params['orderId']);

                static $calls = 0;

                return ++$calls === 1 ? 1 : 0;
            });
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(static::once())->method('dispatch')->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));
        $receiver = $this->receiver($connection, $bus);

        static::assertSame(WebhookReceiver::ACCEPTED, $receiver->receive($body, hash_hmac('sha512', $body, self::KEY), 'sc-1'));
        static::assertSame(WebhookReceiver::DUPLICATE, $receiver->receive($body, hash_hmac('sha512', $body, self::KEY), 'sc-1'));
    }

    public function testInvalidSignatureStoresNothing(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('executeStatement');

        static::assertSame(WebhookReceiver::INVALID_SIGNATURE, $this->receiver($connection)->receive('{}', 'nope', null));
    }

    public function testSignedButUnreadableBodyIsRejected(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('executeStatement');

        static::assertSame(WebhookReceiver::INVALID_PAYLOAD, $this->receiver($connection)->receive('not json', hash_hmac('sha512', 'not json', self::KEY), null));
    }

    public function testBodyWithoutEventOrOrderIsStoredAsUnknown(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('executeStatement')->willReturnCallback(static function (string $sql, array $params): int {
            static::assertSame('unknown', $params['event']);
            static::assertNull($params['orderId']);

            return 1;
        });
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static fn (object $m): Envelope => new Envelope($m));

        static::assertSame(WebhookReceiver::ACCEPTED, $this->receiver($connection, $bus)->receive('{}', hash_hmac('sha512', '{}', self::KEY), null));
    }

    public function testLastReceivedAt(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('2026-11-03 12:00:00.000', null);
        $receiver = $this->receiver($connection);

        static::assertSame('2026-11-03T12:00:00+00:00', $receiver->lastReceivedAt());
        static::assertNull($receiver->lastReceivedAt());
    }

    private function receiver(Connection $connection, ?MessageBusInterface $bus = null): WebhookReceiver
    {
        return new WebhookReceiver(
            new WebhookSignatureValidator(ClientFactory::config(['sandboxApiKey' => self::KEY])),
            $connection,
            $bus ?? $this->createMock(MessageBusInterface::class),
        );
    }
}
