<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSubscriptionTypeIdColumnIntoPaymentItems extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_items')
            ->addColumn('subscription_type_item_id', 'integer', ['null' => true, 'after' => 'subscription_type_id'])
            ->addForeignKey('subscription_type_item_id', 'subscription_type_items', 'id')
            ->update();
    }
}
