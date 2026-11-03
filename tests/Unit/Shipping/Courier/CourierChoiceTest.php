<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests\Unit\Shipping\Courier;

use Kommandhub\ShipbubbleSW\Shipping\Courier\CourierChoiceListener;
use Kommandhub\ShipbubbleSW\Shipping\Courier\CourierChoiceStorage;
use Kommandhub\ShipbubbleSW\Tests\Unit\Quote\CheckoutFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(CourierChoiceStorage::class)]
#[CoversClass(CourierChoiceListener::class)]
class CourierChoiceTest extends TestCase
{
    public function testFormat(): void
    {
        static::assertTrue(CourierChoiceStorage::isValid('test_1_courier:test_1_courier'));
        static::assertFalse(CourierChoiceStorage::isValid('no-colon'));
        static::assertFalse(CourierChoiceStorage::isValid('a:b:c'));
        static::assertFalse(CourierChoiceStorage::isValid('a:<script>'));
        static::assertFalse(CourierChoiceStorage::isValid(123));
    }

    public function testListenerStoresAValidChoiceForTheLoggedInCustomer(): void
    {
        $persister = $this->createMock(SalesChannelContextPersister::class);
        $context = CheckoutFixture::context();
        $persister->expects(static::once())->method('save')
            ->with($context->getToken(), [CourierChoiceStorage::KEY => 'c1:s1'], $context->getSalesChannelId(), 'customer-1');

        $this->listen($persister, $context, 'c1:s1');
    }

    public function testListenerIgnoresMissingOrMalformedValues(): void
    {
        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->expects(static::never())->method('save');

        $this->listen($persister, CheckoutFixture::context(), null);
        $this->listen($persister, CheckoutFixture::context(), '9999 NGN');
    }

    public function testLoadReturnsOnlyAValidStoredChoice(): void
    {
        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->method('load')->willReturnOnConsecutiveCalls([CourierChoiceStorage::KEY => 'c1:s1'], [CourierChoiceStorage::KEY => 'bad'], []);
        $storage = new CourierChoiceStorage($persister);
        $context = CheckoutFixture::context();

        static::assertSame('c1:s1', $storage->load($context));
        static::assertNull($storage->load($context));
        static::assertNull($storage->load($context));
    }

    public function testAnAdminActingForTheCustomerIsNotScopedToTheCustomer(): void
    {
        $persister = $this->createMock(SalesChannelContextPersister::class);
        $context = CheckoutFixture::context();
        $context->setPermissions(['skipDeliveryPriceRecalculation' => true]);
        $persister->expects(static::once())->method('load')->with($context->getToken(), $context->getSalesChannelId(), null)->willReturn([]);

        (new CourierChoiceStorage($persister))->load($context);
    }

    private function listen(SalesChannelContextPersister $persister, SalesChannelContext $context, ?string $value): void
    {
        $data = new RequestDataBag($value === null ? [] : [CourierChoiceStorage::KEY => $value]);

        (new CourierChoiceListener(new CourierChoiceStorage($persister)))
            ->onSwitchContext(new SwitchContextEvent($data, $context, new DataValidationDefinition(), $data->all()));
    }
}
