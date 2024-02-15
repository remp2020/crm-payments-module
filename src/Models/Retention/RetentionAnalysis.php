<?php

namespace Crm\PaymentsModule\Models\Retention;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\PaymentsModule\DataProviders\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RetentionAnalysisJobsRepository;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;
use Tracy\ILogger;

class RetentionAnalysis
{
    use NowTrait;

    public const VERSION = 2;

    public const PARTITION_MONTH = 'month';
    public const PARTITION_WEEK = 'week';

    public const PARTITION_OPTIONS = [
        RetentionAnalysis::PARTITION_MONTH,
        RetentionAnalysis::PARTITION_WEEK,
    ];

    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private SubscriptionsRepository $subscriptionsRepository,
        private RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        private DataProviderManager $dataProviderManager,
        private SegmentFactoryInterface $segmentFactory
    ) {
    }

    /**
     * Computes preview of payment counts based parameters entered by users,
     * used later as a basis for retention analysis.
     * @param array $inputParams Form parameters entered by users
     *
     * @return array containing ActiveRows with attributes 'paid_at_year', 'paid_at_partition', 'count'
     */
    public function precalculatePaymentCounts(array $inputParams): array
    {
        [$innerSql, $innerSqlParams] = $this->loadPaymentsSql($inputParams);

        $subQueryName = 't';
        $partitionSql = $this->getSqlPartition($inputParams, $subQueryName);
        $sql = <<<SQL
SELECT {$partitionSql}, COUNT(*) AS count
  FROM ({$innerSql}) {$subQueryName}
  GROUP BY 1,2
  ORDER BY 1,2
SQL;
        return $this->paymentsRepository->getDatabase()->query($sql, ...$innerSqlParams)->fetchAll();
    }

    private function getSqlPartition($inputParams, $subQueryName)
    {
        if ($inputParams['partition'] === self::PARTITION_MONTH) {
            return <<<SQL
                YEAR({$subQueryName}.paid_at) AS paid_at_year, MONTH({$subQueryName}.paid_at) AS paid_at_partition
                SQL;
        }

        if ($inputParams['partition'] === self::PARTITION_WEEK) {
            return <<<SQL
                YEARWEEK({$subQueryName}.paid_at) DIV 100 AS paid_at_year,
                YEARWEEK({$subQueryName}.paid_at, 3) MOD 100 AS paid_at_partition
                SQL;
        }

        throw new \InvalidArgumentException("parameter 'partition' has invalid value " . $inputParams['partition']);
    }

    /**
     * Runs retention analysis for given job record
     * @param ActiveRow $job
     *
     * @return bool
     * @throws JsonException
     */
    public function runJob(ActiveRow $job): bool
    {
        if ($job->state !== RetentionAnalysisJobsRepository::STATE_CREATED) {
            Debugger::log("Job with id #{$job->id} was already run, cannot run again in state {$job->state}.", ILogger::ERROR);
            return false;
        }

        $this->retentionAnalysisJobsRepository->update($job, [
            'state' => RetentionAnalysisJobsRepository::STATE_STARTED,
            'started_at' => new \DateTime(),
        ]);

        $jobParams = Json::decode($job->params, forceArrays: true);

        // Fix missing params from previous versions
        $dirtyFlag = false;
        if (!isset($jobParams['zero_period_length'])) {
            $jobParams['zero_period_length'] = 31;
            $dirtyFlag = true;
        }
        if (!isset($jobParams['period_length'])) {
            $jobParams['period_length'] = 31;
            $dirtyFlag = true;
        }
        if (!isset($jobParams['partition'])) {
            $jobParams['partition'] = self::PARTITION_MONTH;
            $dirtyFlag = true;
        }
        if ($dirtyFlag) {
            $this->retentionAnalysisJobsRepository->update($job, [
                'params' => Json::encode($jobParams)
            ]);
        }

        $now = DateTime::from($this->getNow());
        [$sql, $sqlParams] = $this->loadPaymentsSql($jobParams);

        $payments = $this->paymentsRepository->getDatabase()->query($sql, ...$sqlParams);

        $retention = [];
        foreach ($payments as $record) {
            [$periods, $lastIncomplete] = $this->getPeriods($record->paid_at, $now, $jobParams);
            $paidAtKey = $this->getPartition($record->paid_at, $jobParams);

            foreach ($periods as $periodNumber => $period) {
                if (!array_key_exists($paidAtKey, $retention)) {
                    $retention[$paidAtKey] = [];
                }

                if (!array_key_exists($periodNumber, $retention[$paidAtKey])) {
                    $retention[$paidAtKey][$periodNumber]['count'] = 0;
                    $retention[$paidAtKey][$periodNumber]['users_in_period'] = 0;
                }

                $retention[$paidAtKey][$periodNumber]['users_in_period']++;

                if ($periodNumber === 0) {
                    // Zero-period is included by definition,
                    // since it has 100% of retention rate (user has bought subscription within the period)
                    $retention[$paidAtKey][$periodNumber]['count']++;
                } else {
                    $subscriptionsCount = $this->subscriptionsRepository->getTable()
                        ->where([
                            'user_id = ?' => $record->user_id,
                            'end_time >= ?' => $period[0],
                            'start_time < ?' => $period[1],
                        ])
                        ->count('*');

                    if ($subscriptionsCount > 0) {
                        $retention[$paidAtKey][$periodNumber]['count']++;
                    }

                    if ($lastIncomplete && ($periodNumber === count($periods) - 1)) {
                        $retention[$paidAtKey][$periodNumber]['incomplete'] = true;
                    }
                }
            }
        }

        $this->retentionAnalysisJobsRepository->update($job, [
            'results' => Json::encode(array_filter([
                'retention' => $retention,
                'version' => self::VERSION,
            ])),
            'finished_at' => new \DateTime(),
            'state' => RetentionAnalysisJobsRepository::STATE_FINISHED,
        ]);

        return true;
    }

    private function loadPaymentsSql(array $inputParams): array
    {
        $joins = [];
        $wheres = [];
        $whereParams = [];

        $wheres[] = 'payments.subscription_id IS NOT NULL AND payments.paid_at IS NOT NULL';
        if (!empty($inputParams['min_date_of_payment'])) {
            $wheres[] = 'payments.paid_at >= ?';
            $whereParams[] = [DateTime::from($inputParams['min_date_of_payment'])];
        }

        if (isset($inputParams['previous_user_subscriptions'])) {
            switch ($inputParams['previous_user_subscriptions']) {
                case 'without_previous_subscription':
                    $joins[] = 'LEFT JOIN subscriptions s1 ON payments.user_id = s1.user_id AND s1.created_at < payments.paid_at';
                    $wheres[] = 's1.id IS NULL';
                    break;
                case 'with_previous_subscription_at_least_one_paid':
                    $joins[] = 'LEFT JOIN subscriptions s1 ON payments.user_id = s1.user_id AND s1.created_at < payments.paid_at AND s1.is_paid = 1';
                    $wheres[] = 's1.id IS NOT NULL';
                    break;
                case 'with_previous_subscription_all_unpaid':
                    $joins[] = 'LEFT JOIN subscriptions s1 ON payments.user_id = s1.user_id AND s1.created_at < payments.paid_at AND s1.is_paid = 1';
                    $joins[] = 'LEFT JOIN subscriptions s2 ON payments.user_id = s2.user_id AND s2.created_at < payments.paid_at AND s2.is_paid = 0';
                    $wheres[] = 's1.id IS NULL AND s2.id IS NOT NULL';
                    break;
                default:
                    throw new \InvalidArgumentException("parameter 'previous_user_subscriptions' has invalid value " . $inputParams['previous_user_subscriptions']);
            }
        }

        /** @var RetentionAnalysisDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.retention_analysis', RetentionAnalysisDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $provider->filter($wheres, $whereParams, $joins, $inputParams);
        }

        if (isset($inputParams['segment_code'])) {
            $segment = $this->segmentFactory->buildSegment($inputParams['segment_code']);
            if (!$segment instanceof Segment) {
                throw new \Exception('Retention analysis is only supported with internal CRM Segment implementation: ' . get_class($segment));
            }

            $joins[] = "JOIN ({$segment->query()}) segment_users ON payments.user_id = segment_users.id";
        }

        if (isset($inputParams['user_source'])) {
            $joins[] = "JOIN users ON payments.user_id = users.id";
            $wheres[] = "users.source = ?";
            $whereParams[] = $inputParams['user_source'];
        }

        $joins = implode(' ', $joins);
        $wheres = implode(' AND ', $wheres);

        $sql = <<<SQL
    SELECT MIN(payments.paid_at) as paid_at, payments.user_id FROM payments
    {$joins}
    WHERE {$wheres}  
    GROUP BY payments.user_id
SQL;
        return [$sql, $whereParams];
    }

    private function getPartition(DateTime $paidAt, array $jobParams): string
    {
        if ($jobParams['partition'] === self::PARTITION_WEEK) {
            return $paidAt->format('o-W');
        }

        if ($jobParams['partition'] === self::PARTITION_MONTH) {
            return $paidAt->format('Y-m');
        }

        throw new \InvalidArgumentException("parameter 'partition' has invalid value " . $jobParams['partition']);
    }

    private function getPeriods(DateTime $paidAt, DateTime $upTo, array $jobParams): array
    {
        $zeroPeriodInterval = new \DateInterval('P' . (int) $jobParams['zero_period_length'] . 'D');
        $periodInterval = new \DateInterval('P' . (int) $jobParams['period_length'] . 'D');
        $periods = [];

        $startPeriodIterator = (clone $paidAt)->add($zeroPeriodInterval);
        $lastIncomplete = false;

        // Zero period may have different length
        $periods[] = [clone $paidAt, clone $startPeriodIterator];

        while ($startPeriodIterator < $upTo) {
            $endOfPeriod = (clone $startPeriodIterator)->add($periodInterval);
            if ($endOfPeriod > $upTo) {
                $lastIncomplete = true;
            }

            $periods[] = [clone $startPeriodIterator, $endOfPeriod];
            $startPeriodIterator->add($periodInterval);
        }
        return [$periods, $lastIncomplete];
    }
}
