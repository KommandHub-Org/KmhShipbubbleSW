<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping\Courier;

use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The courier the customer picked, kept in the sales-channel context payload
 * next to the selected shipping method (pattern: Click & Pick).
 *
 * Only the `<courier_id>:<service_code>` key is stored, never a price: the
 * price always comes from the server-side quote (spec §0).
 */
class CourierChoiceStorage
{
    public const KEY = 'kmhShipbubbleCourier';

    private const FORMAT = '/^[A-Za-z0-9_\-.]{1,64}:[A-Za-z0-9_\-.]{1,64}$/';

    public function __construct(private readonly SalesChannelContextPersister $persister)
    {
    }

    /**
     * @phpstan-assert-if-true string $value
     */
    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::FORMAT, $value) === 1;
    }

    public function load(SalesChannelContext $context): ?string
    {
        $value = $this->persister->load($context->getToken(), $context->getSalesChannelId(), $this->customerId($context))[self::KEY] ?? null;

        return self::isValid($value) ? $value : null;
    }

    public function save(SalesChannelContext $context, string $courierKey): void
    {
        $this->persister->save($context->getToken(), [self::KEY => $courierKey], $context->getSalesChannelId(), $this->customerId($context));
    }

    /**
     * Scoped to the customer only when they are really logged in, so an admin
     * acting for a customer never overwrites the customer's own choice.
     */
    private function customerId(SalesChannelContext $context): ?string
    {
        $customer = $context->getCustomer();

        return $customer !== null && $context->getPermissions() === [] ? $customer->getId() : null;
    }
}
