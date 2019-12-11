<?php

use Phinx\Migration\AbstractMigration;

class RecurrentPaymentsCidIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('recurrent_payments')
            ->addIndex('cid')
            ->update();
    }
}
