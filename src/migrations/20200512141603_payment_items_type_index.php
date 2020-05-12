<?php

use Phinx\Migration\AbstractMigration;

class PaymentItemsTypeIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_items')
            ->addIndex('type')
            ->update();
    }
}
