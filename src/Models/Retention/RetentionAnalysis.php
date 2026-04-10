<?php

namespace Crm\PaymentsModule\Models\Retention;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\PaymentsModule\DataProviders\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RetentionAnalysisJobsRepository;
use Crm\SegmentModule\Models\Segment;
use Crm\SegmentModule\Models\SegmentFactoryInterface;
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
        private RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        private DataProviderManager $dataProviderManager,
        private SegmentFactoryInterface $segmentFactory,
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
                YEARWEEK({$subQueryName}.paid_at, 3) DIV 100 AS paid_at_year,
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

        try {
            $jobParams = $this->normalizeJobParams($job);
            $now = DateTime::from($this->getNow());
            [$paymentsSql, $paymentsSqlParams] = $this->loadPaymentsSql($jobParams);

            $retentionSql = $this->buildRetentionSql($jobParams, $paymentsSql, $paymentsSqlParams, $now);
            $retentionSqlParams = array_merge($paymentsSqlParams, [$now, $now]);

            $results = $this->paymentsRepository->getDatabase()->query($retentionSql, ...$retentionSqlParams);
            $retention = $this->buildRetentionFromResults($results);

            $this->retentionAnalysisJobsRepository->update($job, [
                'results' => Json::encode(array_filter([
                    'retention' => $retention,
                    'version' => self::VERSION,
                ])),
                'finished_at' => new \DateTime(),
                'state' => RetentionAnalysisJobsRepository::STATE_FINISHED,
            ]);
        } catch (\Exception $e) {
            Debugger::log("Retention analysis job #{$job->id} failed: " . $e->getMessage(), ILogger::ERROR);
            $this->retentionAnalysisJobsRepository->update($job, [
                'state' => RetentionAnalysisJobsRepository::STATE_FAILED,
                'finished_at' => new \DateTime(),
            ]);
            return false;
        }

        return true;
    }

    private function normalizeJobParams(ActiveRow $job): array
    {
        $jobParams = Json::decode($job->params, forceArrays: true);

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
                'params' => Json::encode($jobParams),
            ]);
        }

        return $jobParams;
    }

    private function buildRetentionSql(array $jobParams, string $paymentsSql, array $paymentsSqlParams, DateTime $now): string
    {
        $zeroPeriodLength = (int) $jobParams['zero_period_length'];
        $periodLength = (int) $jobParams['period_length'];
        $maxPeriods = $this->calculateMaxPeriods($paymentsSql, $paymentsSqlParams, $now, $zeroPeriodLength, $periodLength);
        $partitionKeySql = $this->getPartitionKeySql($jobParams, 'up');

        return <<<SQL
WITH RECURSIVE period_numbers AS (
    SELECT 0 AS period_num
    UNION ALL
    SELECT period_num + 1 FROM period_numbers WHERE period_num < {$maxPeriods}
),
payments_base AS (
    {$paymentsSql}
),
user_periods AS (
    SELECT
        pb.user_id,
        pb.paid_at,
        pn.period_num,
        CASE WHEN pn.period_num = 0 THEN pb.paid_at
             ELSE DATE_ADD(pb.paid_at, INTERVAL ({$zeroPeriodLength} + {$periodLength} * (pn.period_num - 1)) DAY)
        END AS period_start,
        CASE WHEN pn.period_num = 0 THEN DATE_ADD(pb.paid_at, INTERVAL {$zeroPeriodLength} DAY)
             ELSE DATE_ADD(pb.paid_at, INTERVAL ({$zeroPeriodLength} + {$periodLength} * pn.period_num) DAY)
        END AS period_end
    FROM payments_base pb
    CROSS JOIN period_numbers pn
    WHERE CASE WHEN pn.period_num = 0 THEN pb.paid_at
               ELSE DATE_ADD(pb.paid_at, INTERVAL ({$zeroPeriodLength} + {$periodLength} * (pn.period_num - 1)) DAY)
          END < ?
)
SELECT
    {$partitionKeySql} AS partition_key,
    up.period_num,
    COUNT(*) AS users_in_period,
    SUM(
        CASE
            WHEN up.period_num = 0 THEN 1
            WHEN EXISTS (
                SELECT 1 FROM subscriptions s
                WHERE s.user_id = up.user_id
                AND s.end_time >= up.period_start
                AND s.start_time < up.period_end
            ) THEN 1
            ELSE 0
        END
    ) AS retained_count,
    MAX(CASE WHEN up.period_end > ? THEN 1 ELSE 0 END) AS incomplete
FROM user_periods up
GROUP BY partition_key, up.period_num
ORDER BY partition_key, up.period_num
SQL;
    }

    private function calculateMaxPeriods(string $paymentsSql, array $paymentsSqlParams, DateTime $now, int $zeroPeriodLength, int $periodLength): int
    {
        $earliestPaidAtRow = $this->paymentsRepository->getDatabase()
            ->query("SELECT MIN(paid_at) as earliest FROM ({$paymentsSql}) t", ...$paymentsSqlParams)
            ->fetch();

        $earliestDate = $earliestPaidAtRow && $earliestPaidAtRow->earliest !== null ? DateTime::from($earliestPaidAtRow->earliest) : $now;
        $totalDays = max(0, (int) $now->diff($earliestDate)->days);

        if ($periodLength <= 0) {
            return 1;
        }

        return (int) ceil(max(0, $totalDays - $zeroPeriodLength) / $periodLength) + 1;
    }

    private function buildRetentionFromResults(iterable $results): array
    {
        $retention = [];
        foreach ($results as $row) {
            $retention[$row->partition_key][$row->period_num] = [
                'count' => (int) $row->retained_count,
                'users_in_period' => (int) $row->users_in_period,
            ];

            if ($row->incomplete) {
                $retention[$row->partition_key][$row->period_num]['incomplete'] = true;
            }
        }
        return $retention;
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

        if (isset($inputParams['subscription_type'])) {
            $placeholders = [];
            foreach ((array) $inputParams['subscription_type'] as $subscriptionTypeId) {
                $whereParams[] = $subscriptionTypeId;
                $placeholders[] = '?';
            }

            $joins[] = "JOIN subscription_types ON payments.subscription_type_id = subscription_types.id";
            $wheres[] = 'subscription_types.id IN (' . implode(',', $placeholders) . ')';
        }

        if (isset($inputParams['subscription_type_tag'])) {
            $placeholders = [];
            foreach ((array) $inputParams['subscription_type_tag'] as $subscriptionTypeTag) {
                $whereParams[] = $subscriptionTypeTag;
                $placeholders[] = '?';
            }

            $joins[] = "JOIN subscription_type_tags ON payments.subscription_type_id = subscription_type_tags.subscription_type_id";
            $wheres[] = 'subscription_type_tags.tag IN (' . implode(',', $placeholders) . ')';
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

    private function getPartitionKeySql(array $jobParams, string $alias): string
    {
        if ($jobParams['partition'] === self::PARTITION_MONTH) {
            return "DATE_FORMAT({$alias}.paid_at, '%Y-%m')";
        }

        if ($jobParams['partition'] === self::PARTITION_WEEK) {
            return "CONCAT(YEARWEEK({$alias}.paid_at, 3) DIV 100, '-', LPAD(YEARWEEK({$alias}.paid_at, 3) MOD 100, 2, '0'))";
        }

        throw new \InvalidArgumentException("parameter 'partition' has invalid value " . $jobParams['partition']);
    }
}
