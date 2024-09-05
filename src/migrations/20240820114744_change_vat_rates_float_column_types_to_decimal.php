<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ChangeVatRatesFloatColumnTypesToDecimal extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("
ALTER TABLE vat_rates MODIFY COLUMN standard DECIMAL(10,2) NOT NULL, LOCK=SHARED;
ALTER TABLE vat_rates MODIFY COLUMN eperiodical DECIMAL(10,2) NULL, LOCK=SHARED;
ALTER TABLE vat_rates MODIFY COLUMN ebook DECIMAL(10,2) NULL, LOCK=SHARED;
        ");
    }

    public function down(): void
    {
        $this->execute("
ALTER TABLE vat_rates MODIFY COLUMN standard FLOAT NOT NULL, LOCK=SHARED;
ALTER TABLE vat_rates MODIFY COLUMN eperiodical FLOAT NULL, LOCK=SHARED;
ALTER TABLE vat_rates MODIFY COLUMN ebook FLOAT NULL, LOCK=SHARED;
        ");
    }
}
