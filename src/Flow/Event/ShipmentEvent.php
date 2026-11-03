<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Flow\Event;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\OrderAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * A Flow Builder trigger about one Shipbubble shipment (spec §10). Exposes the
 * order and, as template variables, `courierName`, `trackingUrl`, `status`
 * and `errorMessage`. The default mail recipient is the order's customer.
 */
abstract class ShipmentEvent extends Event implements FlowEventAware, OrderAware, MailAware, SalesChannelAware, ScalarValuesAware
{
    public function __construct(
        private readonly OrderEntity $order,
        private readonly Context $context,
        private readonly string $courierName,
        private readonly string $trackingUrl,
        private readonly string $status,
        private readonly string $errorMessage = '',
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->order->getId();
    }

    public function getSalesChannelId(): string
    {
        return $this->order->getSalesChannelId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        $customer = $this->order->getOrderCustomer();

        return new MailRecipientStruct([
            (string)$customer?->getEmail() => trim($customer?->getFirstName() . ' ' . $customer?->getLastName()),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getValues(): array
    {
        return [
            'courierName' => $this->courierName,
            'trackingUrl' => $this->trackingUrl,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('order', new EntityType(OrderDefinition::class))
            ->add('courierName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('trackingUrl', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('status', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('errorMessage', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}
