<?php

use Phinx\Migration\AbstractMigration;

class ClearRetentionJobsAfterBreakingChanges extends AbstractMigration
{
    public function change()
    {
        // There were breaking changes in format used in 'results' column in retention_analysis_jobs table,
        // therefore old analysis are not valid yet.
        // Truncating since the initial version was not officially released yet.
        $this->table('retention_analysis_jobs')->truncate();
    }
}
