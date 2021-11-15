<?php

use Phinx\Migration\AbstractMigration;

class PaymentLogIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_logs')
            ->addForeignKey('payment_id', 'payments')
            ->update();
    }
}
