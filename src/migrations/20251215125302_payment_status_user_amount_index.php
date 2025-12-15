<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PaymentStatusUserAmountIndex extends AbstractMigration
{
    public function up(): void
    {
        $this->table('payments')
            ->addIndex(['status', 'user_id', 'amount'])
            ->update();

        if ($this->table('payments')->hasIndex(['status', 'amount'])) {
            $this->table('payments')
                ->removeIndex(['status', 'amount'])
                ->update();
        }

        if ($this->table('payments')->hasIndex('status')) {
            $this->table('payments')
                ->removeIndex('status')
                ->update();
        }
    }

    public function down(): void
    {
        $this->table('payments')
            ->addIndex(['status'])
            ->update();

        if ($this->table('payments')->hasIndex(['status', 'user_id', 'amount'])) {
            $this->table('payments')
                ->removeIndex(['status', 'user_id', 'amount'])
                ->update();
        }

        if ($this->table('payments')->hasIndex(['status', 'amount'])) {
            $this->table('payments')
                ->removeIndex(['status', 'amount'])
                ->update();
        }
    }
}
