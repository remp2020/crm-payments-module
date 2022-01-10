<?php

use Phinx\Migration\AbstractMigration;

class SalesFunnelIdStatusAmountIndex extends AbstractMigration
{
    public function up()
    {
        $this->table('payments')
            ->addIndex(['sales_funnel_id', 'status', 'amount'])
            ->save();

        $indexExists = $this->table('payments')->hasIndex(['sales_funnel_id']);
        if ($indexExists) {
            $this->table('payments')
                ->removeIndex(['sales_funnel_id'])
                ->save();
        }
    }

    public function down()
    {
        $this->table('payments')
            ->addIndex(['sales_funnel_id'])
            ->save();

        $this->table('payments')
            ->removeIndex(['sales_funnel_id', 'status', 'amount'])
            ->save();
    }
}
