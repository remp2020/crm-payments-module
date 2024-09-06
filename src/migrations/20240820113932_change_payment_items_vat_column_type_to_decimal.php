<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangePaymentItemsVatColumnTypeToDecimal extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
ALTER TABLE `payment_items` ADD COLUMN `vat_decimal` DECIMAL(10,2) AFTER `vat`;
        ");

        // Migrate last two months first
        $sql = <<<SQL
UPDATE `payment_items` 
SET `vat_decimal` = `vat`
WHERE `created_at` >= DATE_SUB(NOW(), INTERVAL 2 MONTH);
SQL;
        $this->execute($sql);

        // Migrate rest of data (by 50000 items to prevent table lock)
        $sql = <<<SQL
UPDATE `payment_items` 
SET `vat_decimal` = `vat`
WHERE `vat_decimal` IS NULL and `vat` IS NOT NULL
LIMIT 50000;
SQL;
        do {
            $rowsCount = $this->execute($sql);
        } while ($rowsCount > 0);

        // Switch columns
        $this->execute("
ALTER TABLE `payment_items` RENAME COLUMN `vat` TO `vat_backup`;
        ");
        $this->execute("
ALTER TABLE `payment_items` RENAME COLUMN `vat_decimal` TO `vat`;
        ");

        // Copy remaining new data
        $sql = <<<SQL
UPDATE `payment_items` 
SET `vat` = `vat_backup`
WHERE `vat` IS NULL and `vat_backup` IS NOT NULL
SQL;
        $rowsCount = $this->execute($sql);

        // Remove old column
        $this->execute("
ALTER TABLE `payment_items` DROP COLUMN `vat_backup`;
        ");
    }

    public function down(): void
    {
        $this->execute("
ALTER TABLE `payment_items` MODIFY `vat` INTEGER, LOCK=SHARED;
        ");
    }
}
