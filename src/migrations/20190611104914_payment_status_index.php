<?php


use Phinx\Migration\AbstractMigration;

class PaymentStatusIndex extends AbstractMigration
{
    public function change()
    {
        $this->table('payments')
            ->addIndex('status')
            ->update();
    }
}
