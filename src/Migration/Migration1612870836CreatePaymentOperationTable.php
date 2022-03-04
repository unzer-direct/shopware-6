<?php


declare(strict_types=1);

namespace UnzerDirect\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1612870836CreatePaymentOperationTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1612870836;
    }
    
    public function update(Connection $connection): void
    {
        $sql =
            <<<SQL
    CREATE TABLE IF NOT EXISTS `unzerdirect_payment_operation` (
        `id` BINARY(16) NOT NULL,
        `version_id` BINARY(16) NOT NULL,
        `unzerdirect_payment_id` BINARY(16) NOT NULL,
        `unzerdirect_payment_version_id` BINARY(16) NOT NULL,
        `unzerdirect_operation_id` INT(11),
        `type` VARCHAR(50) NOT NULL,
        `status` VARCHAR(11),
        `amount` INT(11) NOT NULL,
        `raw_json` TEXT,
        `created_at` DATETIME(3) NOT NULL,
        `updated_at` DATETIME(3),
        PRIMARY KEY (`id`),
        CONSTRAINT `fk.unzerdirect.payment_operation.unzerdirect_payment_id` FOREIGN KEY (`unzerdirect_payment_id`, `unzerdirect_payment_version_id`)
        REFERENCES `unzerdirect_payment` (`id`, `version_id`) ON DELETE CASCADE
    )
    ENGINE = InnoDB
    DEFAULT CHARSET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;
SQL;
        $connection->executeUpdate($sql);
    }
    
    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
