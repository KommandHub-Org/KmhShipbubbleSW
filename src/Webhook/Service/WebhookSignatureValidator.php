<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Webhook\Service;

use Kommandhub\ShipbubbleSW\Setting\Service\Config;

/**
 * Checks that a webhook really comes from Shipbubble (spec §7).
 *
 * ✅ Verified in the sandbox: `x-ship-signature` is the HMAC-SHA512 of the raw
 * body, lowercase hex, keyed with the API key of the mode the webhook belongs
 * to. Shipbubble has no separate webhook secret. One URL serves both modes,
 * so the sales channel's sandbox and live keys are both accepted.
 */
class WebhookSignatureValidator
{
    public const SIGNATURE_HEADER = 'x-ship-signature';

    private const ALGORITHM = 'sha512';

    public function __construct(private readonly Config $config)
    {
    }

    public function isValid(string $rawBody, ?string $signature, ?string $salesChannelId): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        foreach (['sandboxApiKey', 'liveApiKey'] as $key) {
            $secret = $this->config->getString($key, $salesChannelId);

            if ($secret !== '' && hash_equals(hash_hmac(self::ALGORITHM, $rawBody, $secret), strtolower($signature))) {
                return true;
            }
        }

        return false;
    }
}
