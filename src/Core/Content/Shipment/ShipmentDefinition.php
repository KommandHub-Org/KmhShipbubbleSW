<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Core\Content\Shipment;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * DAL definition for `kmh_shipbubble_shipment` — one Shipbubble delivery per
 * order delivery (spec §4): the courier the customer chose, the quote it was
 * priced from, and, once booked, Shipbubble's order id, status and tracking.
 *
 * `request_token` is deliberately not ApiAware: it is a booking credential.
 */
class ShipmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'kmh_shipbubble_shipment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return ShipmentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ShipmentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),
            (new FkField('order_delivery_id', 'orderDeliveryId', OrderDeliveryDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ReferenceVersionField(OrderDeliveryDefinition::class))->addFlags(new Required()),
            (new IdField('sales_channel_id', 'salesChannelId'))->addFlags(new Required(), new ApiAware()),
            (new StringField('mode', 'mode', 8))->addFlags(new Required(), new ApiAware()),
            (new StringField('status', 'status', 24))->addFlags(new Required(), new ApiAware()),
            (new StringField('courier_id', 'courierId', 64))->addFlags(new ApiAware()),
            (new StringField('courier_name', 'courierName'))->addFlags(new ApiAware()),
            (new StringField('service_code', 'serviceCode', 64))->addFlags(new ApiAware()),
            new StringField('request_token', 'requestToken'),
            (new DateTimeField('quoted_at', 'quotedAt'))->addFlags(new ApiAware()),
            (new FloatField('quoted_amount', 'quotedAmount'))->addFlags(new ApiAware()),
            (new FloatField('charged_amount', 'chargedAmount'))->addFlags(new ApiAware()),
            (new StringField('currency', 'currency', 3))->addFlags(new ApiAware()),
            (new StringField('shipbubble_order_id', 'shipbubbleOrderId', 64))->addFlags(new ApiAware()),
            (new StringField('tracking_url', 'trackingUrl', 1024))->addFlags(new ApiAware()),
            (new StringField('waybill_url', 'waybillUrl', 1024))->addFlags(new ApiAware()),
            (new BoolField('cod', 'cod'))->addFlags(new ApiAware()),
            (new StringField('insurance_code', 'insuranceCode', 64))->addFlags(new ApiAware()),
            (new StringField('error_code', 'errorCode', 64))->addFlags(new ApiAware()),
            (new StringField('error_message', 'errorMessage', 1024))->addFlags(new ApiAware()),
            (new JsonField('last_payload', 'lastPayload'))->addFlags(new ApiAware()),
            (new DateTimeField('delivered_at', 'deliveredAt'))->addFlags(new ApiAware()),
            (new DateTimeField('cancelled_at', 'cancelledAt'))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new OneToOneAssociationField('orderDelivery', 'order_delivery_id', 'id', OrderDeliveryDefinition::class, false),
        ]);
    }
}
