<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExpandPaymentMetaValueColumn extends AbstractMigration
{
    public function up(): void
    {
        $this->table('payment_meta')
            ->changeColumn('value', 'string', ['limit' => 500, 'null' => false])
            ->update();
    }

    public function down(): void
    {
        $this->table('payment_meta')
            ->changeColumn('value', 'string', ['limit' => 255, 'null' => false])
            ->update();
    }
}
