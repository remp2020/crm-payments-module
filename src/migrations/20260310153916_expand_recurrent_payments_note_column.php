<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExpandRecurrentPaymentsNoteColumn extends AbstractMigration
{
    public function up(): void
    {
        $this->table('recurrent_payments')
            ->changeColumn('note', 'string', ['limit' => 1000, 'null' => true])
            ->update();
    }

    public function down(): void
    {
        $this->table('recurrent_payments')
            ->changeColumn('note', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
