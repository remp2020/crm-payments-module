<?php

use Phinx\Migration\AbstractMigration;

class RetentionAnalysisJobs extends AbstractMigration
{
    public function change()
    {
        $this->table('retention_analysis_jobs')
            ->addColumn('state', 'enum', [
                'null' => false,
                'values' => [
                    'created',
                    'started',
                    'finished',
                    'failed',
                ]
            ])
            ->addColumn('name', 'string', ['null' => false])
            ->addColumn('params', 'text', ['null' => false])
            ->addColumn('results', 'text', ['null' => true, 'limit' => 'TEXT_MEDIUM'])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->create();
    }
}
