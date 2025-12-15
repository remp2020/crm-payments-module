<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PaymentStatusAmountIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->table('payments')
            ->addIndex(['status', 'amount'])
            ->removeIndex('status')
            ->update();
    }

    public function down(): void
    {
        $this->table('payments')
            ->addIndex('status')
            ->removeIndex(['status', 'amount'])
            ->update();
    }
}
