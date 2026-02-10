<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddChainIdIndexToRecurrentPayments extends AbstractMigration
{
    public function up(): void
    {
        $this->table('recurrent_payments')
            ->addIndex('chain_id')
            ->update();
    }

    public function down(): void
    {
        $this->table('recurrent_payments')
            ->removeIndex(['chain_id'])
            ->update();
    }
}
