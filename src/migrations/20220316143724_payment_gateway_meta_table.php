<?php

use Phinx\Migration\AbstractMigration;

class RecurrentPaymentStateIndex extends AbstractMigration
{
    public function up()
    {
        if ($this->hasTable('payment_gateway_meta')) {
            return;
        }

        $this->table('payment_gateway_meta')
            ->addColumn('payment_gateway_id', 'integer', ['null' => false])
            ->addColumn('key', 'string', ['null' => false, 'limit' => 255])
            ->addColumn('value', 'string', ['null' => false, 'limit' => 255])
            ->addForeignKey('payment_gateway_id', 'payment_gateways', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down()
    {
        $this->table('payment_gateway_meta')->drop()->save();
    }
}
