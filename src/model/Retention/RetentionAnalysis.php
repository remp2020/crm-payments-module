<?php

namespace Crm\PaymentsModule\Retention;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RetentionAnalysisJobsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class RetentionAnalysis
{
    private $paymentsRepository;

    private $dataProviderManager;

    private $retentionAnalysisJobsRepository;

    private $subscriptionsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        SubscriptionsRepository $subscriptionsRepository,
        RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        DataProviderManager $dataProviderManager
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->retentionAnalysisJobsRepository = $retentionAnalysisJobsRepository;
        $this->subscriptionsRepository = $subscriptionsRepository;
    }

    /**
     * Computes preview of monthly payment counts, used later as a basis for retention analysis.
     * @param array $inputParams Form parameters entered by users
     *
     * @return array containing IRows with attributes 'paid_at_year', 'paid_at_month', 'count'
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
     * Runs retention analysis computation on a given job (from retention_analysis_jobs table)
     * @param ActiveRow $job
     *
     * @return bool
     * @throws \Nette\Utils\JsonException
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

        $params = Json::decode($job->params, Json::FORCE_ARRAY);
        $now = new DateTime();
        [$sql, $sqlParams] = $this->loadPaymentsSql($params);

        $payments = $this->paymentsRepository->getDatabase()->query($sql, ...$sqlParams);

        $results = [];
        foreach ($payments as $record) {
            $paidAtKey = $record->paid_at->format('Y-m');
            if (!array_key_exists($paidAtKey, $results)) {
                $results[$paidAtKey] = [];
            }

            $periods = $this->getPeriods($record->paid_at, $now);
            foreach ($periods as $i => $period) {
                $subscriptionsCount = $this->subscriptionsRepository->getTable()
                    ->where([
                        'user_id = ?' => $record->user_id,
                        'start_time >= ?' => $period[0],
                        'end_time >= ?' => $period[1],
                    ])
                    ->count('*');

                if (!array_key_exists($i, $results[$paidAtKey])) {
                    $results[$paidAtKey][$i] = 0;
                }
                $results[$paidAtKey][$i] += $subscriptionsCount > 0 ? 1 : 0;
            }
        }

        $this->retentionAnalysisJobsRepository->update($job, [
            'results' => Json::encode($results),
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

        $wheres[] = 'subscription_id IS NOT NULL';
        if (!empty($inputParams['min_date_of_payment'])) {
            $wheres[] = 'paid_at >= ?';
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

    private function getPeriods(DateTime $paidAt, DateTime $upTo): array
    {
        $periodInterval = new \DateInterval('P31D');
        $periods = [];

        $periodEndIterator = (clone $paidAt)->add($periodInterval);
        while ($periodEndIterator <= $upTo) {
            $periods[] = [(clone $periodEndIterator)->sub($periodInterval), clone $periodEndIterator];
            $periodEndIterator->add($periodInterval);
        }
        return $periods;
    }
}
