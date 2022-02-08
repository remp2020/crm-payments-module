<?php

use Phinx\Migration\AbstractMigration;

class RecurrentPaymentStateIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('recurrent_payments')
            ->addIndex('state')
            ->update();
    }
}
