<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates `kmh_shipbubble_address`: the Shipbubble address code of every
 * customer address the plugin validated. Shipbubble charges for each
 * validation and its codes never expire, so a code is kept for good.
 * Only a hash of the address and contact details is stored.
 */
class Migration1794227295CreateAddressTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794227295;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_shipbubble_address` (
                `sales_channel_id` BINARY(16)  NOT NULL,
                `mode`             VARCHAR(8)  NOT NULL,
                `address_hash`     CHAR(64)    NOT NULL,
                `address_code`     VARCHAR(64) NOT NULL,
                `created_at`       DATETIME(3) NOT NULL,
                PRIMARY KEY (`sales_channel_id`, `mode`, `address_hash`),
                CONSTRAINT `fk.kmh_sb_address.sales_channel` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally empty — nothing destructive to do.
    }
}
