<?php

use Phinx\Migration\AbstractMigration;

class ExpandPaymentsRefererColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('payments')
            ->changeColumn('referer', 'string', ['limit' => 2000, 'null' => true])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('<error>Data rollback is risky. See migration class for details. Nothing done.</error>');
        // remove return if you are 100% sure you know what you are doing
        return;

        // ensure we have only 255 chars long referers
        $this->execute(<<<SQL
            UPDATE `payments`
            SET `referer` = SUBSTR(`referer`, 1, 255)
            WHERE CHAR_LENGTH(`referer`) > 255;
SQL);

        // update column size back to VARCHAR(255)
        $this->table('payments')
            ->changeColumn('referer', 'string', ['limit' => 255, 'null' => true])
            ->update();
    }
}
