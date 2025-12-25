<?php declare(strict_types=1);

namespace WSCPlugin\SWVariantUpdater\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1766651894CreateProgressTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1766651894;
    }

    public function update(Connection $connection): void
    {
        // Create variant_update_progress table
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `wsc_variant_update_progress` (
    `id` BINARY(16) NOT NULL,
    `batch_id` VARCHAR(255) NOT NULL,
    `total_products` INT NOT NULL,
    `processed_products` INT NOT NULL DEFAULT 0,
    `failed_products` INT NOT NULL DEFAULT 0,
    `current_batch_size` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
    `started_at` DATETIME(3) NULL,
    `finished_at` DATETIME(3) NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_batch_id` (`batch_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);

        // Create variant_update_log table
        $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS `wsc_variant_update_log` (
    `id` BINARY(16) NOT NULL,
    `batch_id` VARCHAR(255) NOT NULL,
    `product_number` VARCHAR(255) NULL,
    `error_message` TEXT NULL,
    `stack_trace` LONGTEXT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->executeStatement($sql);
    }

    public function updateDestructive(Connection $connection): void
    {
        // Intentionally left empty - no destructive changes needed
    }
}
