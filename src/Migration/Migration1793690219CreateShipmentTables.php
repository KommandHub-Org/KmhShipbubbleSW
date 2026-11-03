<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Creates `kmh_shipbubble_shipment` (one per order delivery, keyed to the
 * versioned `order_delivery`, cascade-deleted with it) and
 * `kmh_shipbubble_webhook_event` (received webhooks, unique per body hash).
 * Spec §4.
 */
class Migration1793690219CreateShipmentTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793690219;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_shipbubble_shipment` (
                `id`                        BINARY(16)    NOT NULL,
                `order_id`                  BINARY(16)    NOT NULL,
                `order_version_id`          BINARY(16)    NOT NULL,
                `order_delivery_id`         BINARY(16)    NOT NULL,
                `order_delivery_version_id` BINARY(16)    NOT NULL,
                `sales_channel_id`          BINARY(16)    NOT NULL,
                `mode`                      VARCHAR(8)    NOT NULL,
                `status`                    VARCHAR(24)   NOT NULL,
                `courier_id`                VARCHAR(64)   NULL,
                `courier_name`              VARCHAR(255)  NULL,
                `service_code`              VARCHAR(64)   NULL,
                `request_token`             VARCHAR(255)  NULL,
                `quoted_at`                 DATETIME(3)   NULL,
                `quoted_amount`             DOUBLE        NULL,
                `charged_amount`            DOUBLE        NULL,
                `currency`                  CHAR(3)       NULL,
                `shipbubble_order_id`       VARCHAR(64)   NULL,
                `tracking_url`              VARCHAR(1024) NULL,
                `waybill_url`               VARCHAR(1024) NULL,
                `cod`                       TINYINT(1)    NOT NULL DEFAULT 0,
                `insurance_code`            VARCHAR(64)   NULL,
                `error_code`                VARCHAR(64)   NULL,
                `error_message`             VARCHAR(1024) NULL,
                `last_payload`              JSON          NULL,
                `delivered_at`              DATETIME(3)   NULL,
                `cancelled_at`              DATETIME(3)   NULL,
                `created_at`                DATETIME(3)   NOT NULL,
                `updated_at`                DATETIME(3)   NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kmh_sb_shipment.delivery` (`order_delivery_id`, `order_delivery_version_id`),
                UNIQUE KEY `uniq.kmh_sb_shipment.sb_order` (`shipbubble_order_id`),
                KEY `idx.kmh_sb_shipment.status` (`status`),
                CONSTRAINT `fk.kmh_sb_shipment.order` FOREIGN KEY (`order_id`, `order_version_id`)
                    REFERENCES `order` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.kmh_sb_shipment.order_delivery` FOREIGN KEY (`order_delivery_id`, `order_delivery_version_id`)
                    REFERENCES `order_delivery` (`id`, `version_id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);

        $connection->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `kmh_shipbubble_webhook_event` (
                `id`                  BINARY(16)  NOT NULL,
                `idempotency_key`     CHAR(64)    NOT NULL,
                `event`               VARCHAR(64) NOT NULL,
                `shipbubble_order_id` VARCHAR(64) NULL,
                `payload`             JSON        NOT NULL,
                `received_at`         DATETIME(3) NOT NULL,
                `processed_at`        DATETIME(3) NULL,
                `error`               LONGTEXT    NULL,
                `created_at`          DATETIME(3) NOT NULL,
                `updated_at`          DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.kmh_sb_webhook_event.key` (`idempotency_key`),
                KEY `idx.kmh_sb_webhook_event.received_at` (`received_at`)
            ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally empty — nothing destructive to do.
    }
}
