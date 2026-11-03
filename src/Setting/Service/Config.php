<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Setting\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    public const KEY = 'KmhShipbubbleSW.config.';

    /**
     * Values the plugin writes itself. Kept outside the config domain: the
     * configuration page loads and saves back every key under KEY, so a value
     * stored there is overwritten with whatever the page loaded.
     */
    public const STATE_KEY = 'KmhShipbubbleSW.state.';

    public const MODE_SANDBOX = 'sandbox';

    public const MODE_LIVE = 'live';

    /**
     * @param SystemConfigService $systemConfigService Shopware system config service.
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    /**
     * Retrieves a configuration value by key.
     *
     * @param string $key Configuration key (without prefix).
     * @param array|bool|float|int|string|null $default Default value if config is not set.
     * @param string|null $salesChannelId Optional sales channel ID.
     *
     * @return array|bool|float|int|string|null The configuration value or default.
     */
    public function get(string $key, array|bool|float|int|string|null $default = null, ?string $salesChannelId = null): array|bool|float|int|string|null
    {
        $value = $this->systemConfigService->get(self::KEY . $key, $salesChannelId);

        return $value ?? $default;
    }

    /**
     * Retrieves a configuration value by key as string.
     *
     * @param string $key Configuration key (without prefix).
     * @param string|null $salesChannelId Optional sales channel ID.
     *
     * @return string The configuration value as string.
     */
    public function getString(string $key, ?string $salesChannelId = null): string
    {
        $value = $this->systemConfigService->get(self::KEY . $key, $salesChannelId);

        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * Retrieves a boolean configuration value by key.
     *
     * @param string $key Configuration key (without prefix).
     * @param string|null $salesChannelId Optional sales channel ID.
     *
     * @return bool The configuration value as boolean.
     */
    public function getBool(string $key, ?string $salesChannelId = null): bool
    {
        return $this->systemConfigService->getBool(self::KEY . $key, $salesChannelId);
    }

    /**
     * Retrieves an array configuration value by key.
     *
     * @param string $key Configuration key (without prefix).
     * @param string|null $salesChannelId Optional sales channel ID.
     *
     * @return array The configuration value as array.
     */
    public function getArray(string $key, ?string $salesChannelId = null): array
    {
        $value = $this->systemConfigService->get(self::KEY . $key, $salesChannelId);

        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    /**
     * Live only when the merchant explicitly chose it; anything else (unset,
     * unknown value) stays on the sandbox so a misconfiguration never books
     * real shipments.
     */
    public function getMode(?string $salesChannelId = null): string
    {
        return $this->getString('mode', $salesChannelId) === self::MODE_LIVE ? self::MODE_LIVE : self::MODE_SANDBOX;
    }

    public function getApiKey(?string $salesChannelId = null): string
    {
        return $this->getMode($salesChannelId) === self::MODE_LIVE
            ? $this->getString('liveApiKey', $salesChannelId)
            : $this->getString('sandboxApiKey', $salesChannelId);
    }

    /**
     * The sender's Shipbubble address code, stored per mode because a sandbox
     * code is meaningless to the live API and vice versa.
     */
    public function getSenderAddressCode(?string $salesChannelId = null): string
    {
        $value = $this->systemConfigService->get(self::STATE_KEY . $this->senderAddressCodeKey($salesChannelId), $salesChannelId);

        return is_string($value) ? $value : '';
    }

    public function setSenderAddressCode(string $addressCode, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::STATE_KEY . $this->senderAddressCodeKey($salesChannelId), $addressCode, $salesChannelId);
    }

    public function getFloat(string $key, float $default, ?string $salesChannelId = null): float
    {
        $value = $this->systemConfigService->get(self::KEY . $key, $salesChannelId);

        return is_numeric($value) ? (float)$value : $default;
    }

    public function getInt(string $key, int $default, ?string $salesChannelId = null): int
    {
        $value = $this->systemConfigService->get(self::KEY . $key, $salesChannelId);

        return is_numeric($value) ? (int)$value : $default;
    }

    private function senderAddressCodeKey(?string $salesChannelId): string
    {
        return $this->getMode($salesChannelId) === self::MODE_LIVE ? 'senderAddressCodeLive' : 'senderAddressCodeSandbox';
    }
}
