<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Webhook\Service;

use Kommandhub\ShipbubbleSW\Setting\Service\Config;
use Kommandhub\ShipbubbleSW\Tests\Unit\Client\ClientFactory;
use Kommandhub\ShipbubbleSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookSignatureValidator::class)]
#[UsesClass(Config::class)]
class WebhookSignatureValidatorTest extends TestCase
{
    private const BODY = '{"event":"shipment.status.changed"}';

    public function testSignedWithEitherModesKeyPasses(): void
    {
        $validator = $this->validator(['sandboxApiKey' => 'sb_sandbox_k', 'liveApiKey' => 'sb_prod_k']);

        static::assertTrue($validator->isValid(self::BODY, hash_hmac('sha512', self::BODY, 'sb_sandbox_k'), null));
        static::assertTrue($validator->isValid(self::BODY, strtoupper(hash_hmac('sha512', self::BODY, 'sb_prod_k')), null));
    }

    public function testWrongMissingOrUnconfiguredSignaturesFail(): void
    {
        $validator = $this->validator(['sandboxApiKey' => 'sb_sandbox_k']);

        static::assertFalse($validator->isValid(self::BODY, hash_hmac('sha256', self::BODY, 'sb_sandbox_k'), null));
        static::assertFalse($validator->isValid(self::BODY . ' ', hash_hmac('sha512', self::BODY, 'sb_sandbox_k'), null));
        static::assertFalse($validator->isValid(self::BODY, null, null));
        static::assertFalse($validator->isValid(self::BODY, '', null));
        static::assertFalse($this->validator([])->isValid(self::BODY, hash_hmac('sha512', self::BODY, ''), null));
    }

    /**
     * @param array<string, string> $keys
     */
    private function validator(array $keys): WebhookSignatureValidator
    {
        return new WebhookSignatureValidator(ClientFactory::config($keys));
    }
}
