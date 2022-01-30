<?php

namespace Crm\PaymentsModule\Repository;

use Crm\ApplicationModule\Repository;
use Crm\ApplicationModule\Selection;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

class RetentionAnalysisJobsRepository extends Repository
{
    const STATE_CREATED = 'created';
    const STATE_STARTED = 'started';
    const STATE_FINISHED = 'finished';
    const STATE_FAILED = 'failed';

    protected $tableName = 'retention_analysis_jobs';

    final public function all(): Selection
    {
        return $this->getTable()->order('created_at DESC');
    }

    final public function add(string $name, string $params)
    {
        return $this->insert([
            'state' => self::STATE_CREATED,
            'name' => $name,
            'params' => $params,
            'created_at' => new \DateTime(),
            'updated_at' => new \DateTime(),
        ]);
    }

    final public function setFailed($jobId, string $error)
    {
        $job = $this->find($jobId);

        if ($job) {
            if ($job->state !== self::STATE_FINISHED) {
                $this->update($job, [
                    'state' => self::STATE_FAILED,
                    'results' => Json::encode(['error' => $error]),
                ]);
            }
        } else {
            throw new \InvalidArgumentException("Retention analysis job with ID #{$jobId} does not exist.");
        }
    }

    final public function update(ActiveRow &$row, $data)
    {
        $data['updated_at'] = new \DateTime();
        return parent::update($row, $data);
    }
}
