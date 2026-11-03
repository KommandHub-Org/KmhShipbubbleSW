<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow;

use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCancelledEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentCreatedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentDeliveredEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentFailedEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentInTransitEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentPickedUpEvent;
use Kommandhub\ShipbubbleSW\Flow\Event\ShipmentReturnedEvent;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Makes the plugin's events selectable as Flow Builder triggers.
 */
class FlowEventSubscriber implements EventSubscriberInterface
{
    private const EVENTS = [
        ShipmentCreatedEvent::class,
        ShipmentFailedEvent::class,
        ShipmentPickedUpEvent::class,
        ShipmentInTransitEvent::class,
        ShipmentDeliveredEvent::class,
        ShipmentReturnedEvent::class,
        ShipmentCancelledEvent::class,
    ];

    public function __construct(private readonly BusinessEventCollector $collector)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [BusinessEventCollectorEvent::NAME => 'onCollect'];
    }

    public function onCollect(BusinessEventCollectorEvent $event): void
    {
        foreach (self::EVENTS as $eventClass) {
            $definition = $this->collector->define($eventClass);

            if ($definition !== null) {
                $event->getCollection()->set($definition->getName(), $definition);
            }
        }
    }
}
