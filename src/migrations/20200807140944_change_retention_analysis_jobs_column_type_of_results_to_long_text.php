<?php

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

class ChangeRetentionAnalysisJobsColumnTypeOfResultsToLongText extends AbstractMigration
{
    public function up()
    {
        $this->table('retention_analysis_jobs')
            ->changeColumn('results', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->update();
    }

    public function down()
    {
        $this->table('retention_analysis_jobs')
            ->changeColumn('results', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->update();
    }
}