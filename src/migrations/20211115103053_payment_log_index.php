<?php

use Phinx\Migration\AbstractMigration;

class PaymentLogIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_logs')
            ->addIndex('payment_id')
            ->update();
    }
}
