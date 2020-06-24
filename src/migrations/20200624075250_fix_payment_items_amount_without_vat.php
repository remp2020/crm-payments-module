<?php

use Phinx\Migration\AbstractMigration;

class FixPaymentItemsAmountWithoutVat extends AbstractMigration
{
    public function change()
    {
        $sql = <<<SQL
UPDATE `payment_items` 
SET `amount_without_vat` = ROUND(`amount` / (1 + (`vat`/100)), 2); 
SQL;
        $this->execute($sql);

    }
}
