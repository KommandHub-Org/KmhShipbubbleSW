<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Quote;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Tells the merchant in the Administration when checkout cannot price
 * Shipbubble delivery for a reason only they can fix (spec §3). At most one
 * notification per sales channel and reason per hour.
 */
class FailureNotifier
{
    private const THROTTLE_TTL = 3600;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly CacheInterface $cache,
    ) {
    }

    public function notify(QuoteError $error, string $salesChannelId, Context $context): void
    {
        if (!$error->notifiesAdmin()) {
            return;
        }

        $this->cache->get(
            'kmh_sb_notified_' . $salesChannelId . '_' . $error->value,
            function (ItemInterface $item) use ($error, $context): bool {
                $item->expiresAfter(self::THROTTLE_TTL);

                $this->notificationService->createNotification([
                    'id' => Uuid::randomHex(),
                    'status' => 'danger',
                    'message' => $error === QuoteError::NotConfigured
                        ? 'Shipbubble courier delivery is selected at checkout but not set up: check the API key and validate the sender address in the Shipbubble settings.'
                        : 'Shipbubble could not be reached at checkout, so customers could not get a courier price. If this keeps happening, check the Shipbubble status and your wallet.',
                    'adminOnly' => true,
                    'requiredPrivileges' => [],
                ], $context);

                return true;
            },
        );
    }
}
