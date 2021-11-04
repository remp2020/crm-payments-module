<?php

use Phinx\Migration\AbstractMigration;

class AddIndexToPaymentMetaKeyColumn extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_meta')
            ->addIndex('key')
            ->update();
    }
}
