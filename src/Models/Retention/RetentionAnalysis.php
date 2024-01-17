<?php

namespace Crm\PaymentsModule\Retention;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RetentionAnalysisJobsRepository;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tracy\Debugger;
use Tracy\ILogger;

class RetentionAnalysis
{
    public const VERSION = 2;

    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private SubscriptionsRepository $subscriptionsRepository,
        private RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        private DataProviderManager $dataProviderManager,
        private SegmentFactoryInterface $segmentFactory
    ) {
    }

    /**
     * Computes preview of monthly payment counts, used later as a basis for retention analysis.
     * @param array $inputParams Form parameters entered by users
     *
     * @return array containing ActiveRows with attributes 'paid_at_year', 'paid_at_month', 'count'
     */
    public function precalculateMonthlyPaymentCounts(array $inputParams): array
    {
        [$innerSql, $innerSqlParams] = $this->loadPaymentsSql($inputParams);

        $sql = <<<SQL
SELECT YEAR(t.paid_at) AS paid_at_year, MONTH(t.paid_at) AS paid_at_month, COUNT(*) AS count
  FROM ({$innerSql}) t
  GROUP BY YEAR(t.paid_at), MONTH(t.paid_at)
SQL;
        return $this->paymentsRepository->getDatabase()->query($sql, ...$innerSqlParams)->fetchAll();
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

        $jobParams = Json::decode($job->params, Json::FORCE_ARRAY);

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
        if ($dirtyFlag) {
            $this->retentionAnalysisJobsRepository->update($job, [
                'params' => Json::encode($jobParams)
            ]);
        }

        $now = new DateTime();
        [$sql, $sqlParams] = $this->loadPaymentsSql($jobParams);

        $payments = $this->paymentsRepository->getDatabase()->query($sql, ...$sqlParams);

        $retention = [];
        foreach ($payments as $record) {
            [$periods, $lastIncomplete] = $this->getPeriods($record->paid_at, $now, $jobParams);
            $paidAtKey = $record->paid_at->format('Y-m');

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
