<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Core\Content\Shipment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ShipmentEntity>
 */
class ShipmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ShipmentEntity::class;
    }
}
