<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Webhook\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Webhook\Message\ShipbubbleWebhookMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Verifies, stores and queues one webhook (spec §7). The body hash is the
 * idempotency key: a redelivered webhook is acknowledged and not processed
 * again.
 */
class WebhookReceiver
{
    public const ACCEPTED = 'accepted';

    public const DUPLICATE = 'duplicate';

    public const INVALID_SIGNATURE = 'invalid_signature';

    public const INVALID_PAYLOAD = 'invalid_payload';

    public function __construct(
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly Connection $connection,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function receive(string $rawBody, ?string $signature, ?string $salesChannelId): string
    {
        if (!$this->signatureValidator->isValid($rawBody, $signature, $salesChannelId)) {
            return self::INVALID_SIGNATURE;
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            return self::INVALID_PAYLOAD;
        }

        $id = Uuid::randomHex();
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $inserted = $this->connection->executeStatement(
            'INSERT IGNORE INTO `kmh_shipbubble_webhook_event`
                (`id`, `idempotency_key`, `event`, `shipbubble_order_id`, `payload`, `received_at`, `created_at`)
             VALUES (:id, :key, :event, :orderId, :payload, :now, :now)',
            [
                'id' => Uuid::fromHexToBytes($id),
                'key' => hash('sha256', $rawBody),
                'event' => mb_substr(is_string($payload['event'] ?? null) ? $payload['event'] : 'unknown', 0, 64),
                'orderId' => is_string($payload['order_id'] ?? null) ? mb_substr($payload['order_id'], 0, 64) : null,
                'payload' => $rawBody,
                'now' => $now,
            ],
        );

        if ($inserted === 0) {
            return self::DUPLICATE;
        }

        $this->messageBus->dispatch(new ShipbubbleWebhookMessage($id));

        return self::ACCEPTED;
    }

    /**
     * When the last webhook arrived, for the configuration page.
     */
    public function lastReceivedAt(): ?string
    {
        $value = $this->connection->fetchOne('SELECT MAX(`received_at`) FROM `kmh_shipbubble_webhook_event`');

        // Stored in UTC; returned with its offset so the browser shows local time.
        return is_string($value) ? (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->format(\DATE_ATOM) : null;
    }
}
