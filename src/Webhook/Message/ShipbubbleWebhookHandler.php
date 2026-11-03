<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Webhook\Message;

use Doctrine\DBAL\Connection;
use Kommandhub\ShipbubbleSW\Client\Dto\Shipment;
use Kommandhub\ShipbubbleSW\Shipment\Service\ShipmentService;
use Kommandhub\ShipbubbleSW\Shipment\ShipmentStatus;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Processes one stored webhook (spec §7): finds the shipment by Shipbubble
 * order id, applies the new status and marks the event processed. Anything
 * that cannot be applied is noted on the event, never retried in a loop.
 */
#[AsMessageHandler]
class ShipbubbleWebhookHandler
{
    private const STATUS_EVENTS = ['shipment.label.created', 'shipment.status.changed', 'shipment.cancelled'];

    public function __construct(
        private readonly Connection $connection,
        private readonly ShipmentService $shipmentService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ShipbubbleWebhookMessage $message): void
    {
        $id = Uuid::fromHexToBytes($message->eventId);
        $row = $this->connection->fetchAssociative(
            'SELECT `event`, `payload` FROM `kmh_shipbubble_webhook_event` WHERE `id` = :id AND `processed_at` IS NULL',
            ['id' => $id],
        );

        if ($row === false) {
            return;
        }

        try {
            $note = $this->process(is_string($row['event']) ? $row['event'] : '', json_decode(is_string($row['payload']) ? $row['payload'] : '', true));
        } catch (\Throwable $e) {
            $this->logger->error('Shipbubble webhook could not be processed.', ['eventId' => $message->eventId, 'reason' => $e->getMessage()]);
            $note = 'Failed: ' . $e->getMessage();
        }

        $this->connection->executeStatement(
            'UPDATE `kmh_shipbubble_webhook_event` SET `processed_at` = :now, `error` = :note, `updated_at` = :now WHERE `id` = :id',
            ['now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT), 'note' => $note, 'id' => $id],
        );
    }

    /**
     * @return string|null a note when the event was not applied
     */
    private function process(string $event, mixed $payload): ?string
    {
        if (!in_array($event, self::STATUS_EVENTS, true)) {
            // Includes shipment.cod.remitted: cash on delivery is not offered in v1.
            return 'Event not used by the plugin; stored only.';
        }

        if (!is_array($payload) || !is_string($payload['order_id'] ?? null)) {
            return 'Webhook without a Shipbubble order id.';
        }

        $applied = $this->shipmentService->applyWebhook(
            $payload['order_id'],
            Shipment::fromArray($payload),
            $event === 'shipment.cancelled' ? ShipmentStatus::Cancelled : self::newStatus($payload),
            Context::createCLIContext(),
        );

        return $applied ? null : 'No shipment with this Shipbubble order id.';
    }

    /**
     * The webhook body's `status` is the status before the change; the new one
     * is the last `package_status` entry, a display label ("Picked up").
     *
     * @param array<mixed> $payload
     */
    private static function newStatus(array $payload): ?ShipmentStatus
    {
        $history = is_array($payload['package_status'] ?? null) ? $payload['package_status'] : [];
        $last = end($history);
        $label = is_array($last) && is_string($last['status'] ?? null) ? $last['status'] : '';

        return ShipmentStatus::tryFrom(str_replace(' ', '_', strtolower(trim($label))));
    }
}
