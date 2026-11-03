<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Shipping\Courier;

use Shopware\Core\System\SalesChannel\Event\SwitchContextEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Stores the courier posted with the checkout shipping form. A malformed value
 * is ignored; whether the courier is actually on offer is checked against the
 * quote when the cart is calculated.
 */
class CourierChoiceListener
{
    public function __construct(private readonly CourierChoiceStorage $storage)
    {
    }

    #[AsEventListener(event: SwitchContextEvent::CONSISTENT_CHECK)]
    public function onSwitchContext(SwitchContextEvent $event): void
    {
        $value = $event->getRequestData()->get(CourierChoiceStorage::KEY);

        if (CourierChoiceStorage::isValid($value)) {
            $this->storage->save($event->getSalesChannelContext(), $value);
        }
    }
}
