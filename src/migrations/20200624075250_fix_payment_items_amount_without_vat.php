<?php

use Phinx\Migration\AbstractMigration;

class FixPaymentItemsAmountWithoutVat extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
UPDATE `payment_items` 
SET `amount_without_vat` = ROUND(`amount` / (1 + (`vat`/100)), 2); 
SQL;
        $this->execute($sql);

    }

    public function down()
    {
        $this->output->writeln('Down migration is not available.');
    }
}
