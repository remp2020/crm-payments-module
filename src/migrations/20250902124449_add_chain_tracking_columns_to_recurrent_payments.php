<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddChainTrackingColumnsToRecurrentPayments extends AbstractMigration
{
    public function up(): void
    {
        $this->table('recurrent_payments')
            ->addColumn('chain_id', 'string', ['null' => true, 'after' => 'payment_method_id'])
            ->addColumn('cycle', 'integer', ['null' => true, 'after' => 'chain_id'])
            ->addIndex('cycle')
            ->update();
    }

    public function down(): void
    {
        $this->table('recurrent_payments')
            ->removeIndex(['cycle'])
            ->removeColumn('chain_id')
            ->removeColumn('cycle')
            ->update();
    }
}
