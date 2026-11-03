<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Webhook\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * A stored webhook to process in the background, so Shipbubble gets its 200
 * at once (spec §7, limit 15 s).
 */
final class ShipbubbleWebhookMessage implements AsyncMessageInterface
{
    public function __construct(public readonly string $eventId)
    {
    }
}
