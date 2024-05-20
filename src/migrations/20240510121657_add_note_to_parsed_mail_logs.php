<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddNoteToParsedMailLogs extends AbstractMigration
{
    public function up(): void
    {
        $this->table('parsed_mail_logs')
            ->addColumn('note', 'text', ['null' => true, 'after' => 'message'])
            ->update();
    }

    public function down(): void
    {
        $this->table('parsed_mail_logs')
            ->removeColumn('note')
            ->update();
    }
}
