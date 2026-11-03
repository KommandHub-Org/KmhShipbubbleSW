<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Core\Content\Shipment;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Adds `order_delivery.kmhShipbubbleShipment` (spec §4), loaded only when
 * explicitly associated.
 */
class OrderDeliveryExtension extends EntityExtension
{
    public function getEntityName(): string
    {
        return OrderDeliveryDefinition::ENTITY_NAME;
    }

    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField('kmhShipbubbleShipment', 'id', 'order_delivery_id', ShipmentDefinition::class, false))
                // cloneRelevant: false — otherwise Shopware copies the row (same
                // id) into every order version, so opening the order in the
                // Administration moves the shipment onto the draft version and
                // saving or discarding the draft deletes it (same pitfall as
                // KmhLastMileDeliverySW). Deleting the order still cascades via
                // the database foreign key.
                ->addFlags(new Extension(), new CascadeDelete(false)),
        );
    }
}
