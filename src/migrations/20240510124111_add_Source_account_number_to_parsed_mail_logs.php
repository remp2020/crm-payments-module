<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSourceAccountNumberToParsedMailLogs extends AbstractMigration
{
    public function up(): void
    {
        $this->table('parsed_mail_logs')
            ->addColumn('source_account_number', 'string', ['null' => true, 'after' => 'message'])
            ->update();
    }

    public function down(): void
    {
        $this->table('parsed_mail_logs')
            ->removeColumn('source_account_number')
            ->update();
    }
}
