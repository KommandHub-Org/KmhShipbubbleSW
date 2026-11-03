<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Daily removal of stored webhook events older than the retention period
 * (spec §4). The idempotency check only needs recent events.
 */
class WebhookEventCleanupTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'kmh_shipbubble.webhook_event_cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
