<?php

use Phinx\Migration\AbstractMigration;

class AddIndexToPaymentMetaKeyColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('payment_meta')
            ->addIndex('key')
            ->update();
    }
    
    public function down()
    {
        $this->table('payment_meta')
            ->removeIndex('key')
            ->update();
    }
}
