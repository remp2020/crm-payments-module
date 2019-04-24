<?php


use Phinx\Migration\AbstractMigration;

class RemoveShopColumnFromGatewaysTable extends AbstractMigration
{
    public function up()
    {
        $this->table('payment_gateways')
            ->removeColumn('shop')
            ->update();
    }

    public function down()
    {
        $this->table('payment_gateways')
            ->addColumn('shop', 'boolean', ['null' => false, 'default' => false])
            ->update();
    }
}
