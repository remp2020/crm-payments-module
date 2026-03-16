<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameModifiedAtColumntToUpdatedAtInPaymentTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_gateways')
            ->renameColumn('modified_at', 'updated_at')
            ->update();

        $this->table('payments')
            ->renameColumn('modified_at', 'updated_at')
            ->update();
    }
}
