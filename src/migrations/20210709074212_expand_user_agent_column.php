<?php

use Phinx\Migration\AbstractMigration;

class ExpandUserAgentColumn extends AbstractMigration
{
    public function up()
    {
        $this->table('payments')
            ->changeColumn('user_agent', 'string', ['limit' => 2000, 'null' => false])
            ->update();
    }

    public function down()
    {
        $this->output->writeln('<error>Data rollback is risky. See migration class for details. Nothing done.</error>');
        // remove return if you are 100% sure you know what you are doing
        return;

        // ensure we have only 255 chars long user_agents
        $this->execute(<<<SQL
            UPDATE `payments`
            SET `user_agent` = SUBSTR(`user_agent`, 1, 255)
            WHERE CHAR_LENGTH(`user_agent`) > 255;
SQL);

        // update column size back to VARCHAR(255)
        $this->table('payments')
            ->changeColumn('user_agent', 'string', ['limit' => 255, 'null' => false])
            ->update();
    }
}
