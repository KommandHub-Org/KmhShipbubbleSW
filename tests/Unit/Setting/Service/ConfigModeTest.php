<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Setting\Service;

use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Config::class)]
class ConfigModeTest extends TestCase
{
    public function testUnknownModeFallsBackToSandbox(): void
    {
        $config = ClientFactory::config(['mode' => 'production', 'sandboxApiKey' => 's', 'liveApiKey' => 'l']);

        static::assertSame(Config::MODE_SANDBOX, $config->getMode());
        static::assertSame('s', $config->getApiKey());
    }

    public function testLiveMode(): void
    {
        $config = ClientFactory::config(['mode' => 'live', 'sandboxApiKey' => 's', 'liveApiKey' => 'l']);

        static::assertSame(Config::MODE_LIVE, $config->getMode());
        static::assertSame('l', $config->getApiKey());
    }

    public function testSenderAddressCodeIsStoredPerMode(): void
    {
        $sandbox = ClientFactory::config(['mode' => 'sandbox']);
        $sandbox->setSenderAddressCode('111');

        static::assertSame('111', $sandbox->getSenderAddressCode());
        static::assertSame('111', $sandbox->getString('senderAddressCodeSandbox'));
        static::assertSame('', $sandbox->getString('senderAddressCodeLive'));

        $live = ClientFactory::config(['mode' => 'live']);
        $live->setSenderAddressCode('222');

        static::assertSame('222', $live->getString('senderAddressCodeLive'));
    }

    public function testNumericReaders(): void
    {
        $config = ClientFactory::config(['weight' => '0.75', 'ttl' => 600, 'bad' => 'x']);

        static::assertSame(0.75, $config->getFloat('weight', 0.5));
        static::assertSame(0.5, $config->getFloat('bad', 0.5));
        static::assertSame(600, $config->getInt('ttl', 1800));
        static::assertSame(1800, $config->getInt('missing', 1800));
    }
}
