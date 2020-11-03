<?php

use Phinx\Migration\AbstractMigration;

class CreatePaymentItemsMeta extends AbstractMigration
{
    public function change()
    {
        $this->table('payment_item_meta')
            ->addColumn('payment_item_id', 'integer', ['null' => false])
            ->addColumn('key', 'string', ['null' => false])
            ->addColumn('value', 'string', ['null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addForeignKey('payment_item_id', 'payment_items')
            ->addIndex(['payment_item_id', 'key'], ['unique' => true])
            ->create();
    }
}
