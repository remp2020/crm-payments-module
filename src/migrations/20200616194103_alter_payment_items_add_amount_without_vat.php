<?php

use Phinx\Migration\AbstractMigration;

class AlterPaymentItemsAddAmountWithoutVat extends AbstractMigration
{
    public function up()
    {
        $this->table('payment_items')
            ->addColumn('amount_without_vat', 'decimal', ['after'=> 'amount', 'scale' => 2, 'precision' => 10, 'null' => true])
            ->update();

        $sql = <<<SQL
UPDATE `payment_items` 
SET `amount_without_vat` = ROUND(`amount` * (1 - (`vat`/100)), 2); 
SQL;
        $this->execute($sql);

        $this->table('payment_items')
            ->changeColumn('amount_without_vat', 'decimal', ['scale' => 2, 'precision' => 10, 'null' => false])
            ->update();
    }

    public function down()
    {
        $this->table('payment_items')
            ->removeColumn('amount_without_vat')
            ->update();
    }
}
