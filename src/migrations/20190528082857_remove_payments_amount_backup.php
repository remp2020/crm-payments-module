<?php

use Phinx\Migration\AbstractMigration;

class RemovePaymentsAmountBackup extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('payments');
        if ($table->hasColumn('amount_backup')) {
            $table->removeColumn('amount_backup')
                ->update();
        }
    }

    public function down()
    {
        $this->output->writeln('Down migration is not available. `amount_backup` was legacy backup column not used by CRM code.');
    }
}
