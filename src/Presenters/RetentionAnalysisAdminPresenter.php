<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Forms\RetentionAnalysisFilterFormFactory;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RetentionAnalysisJobsRepository;
use Crm\PaymentsModule\Retention\RetentionAnalysis;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\Json;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tomaj\Hermes\Emitter;

class RetentionAnalysisAdminPresenter extends AdminPresenter
{
    private const SESSION_SECTION = 'retention_analysis';

    /** @var DataProviderManager @inject */
    public $dataProviderManager;

    /** @var SubscriptionTypesRepository @inject */
    public $subscriptionTypesRepository;

    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var SubscriptionsRepository @inject */
    public $subscriptionsRepository;

    /** @var RetentionAnalysisJobsRepository @inject */
    public $retentionAnalysisJobsRepository;

    /** @var RetentionAnalysis @inject */
    public $retentionAnalysis;

    /** @var RetentionAnalysisFilterFormFactory @inject */
    public $retentionAnalysisFilterFormFactory;

    /** @var Emitter @inject */
    public $hermesEmitter;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $jobs = $this->retentionAnalysisJobsRepository->all();

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($jobs->count('*'));
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;
        $this->template->jobs = $jobs->limit(
            $paginator->getLength(),
            $paginator->getOffset()
        );

        $section = $this->getSession(self::SESSION_SECTION);
        $jobIdsToCompare = $section->jobIdsToCompare ?? [];
        if ($jobIdsToCompare) {
            $this->template->jobsToCompare = $this->retentionAnalysisJobsRepository->getTable()->where(['id IN (?)' => $jobIdsToCompare])->fetchAll();
        }
    }

    /**
     * @admin-access-level read
     */
    public function handleClearComparison()
    {
        $section = $this->getSession(self::SESSION_SECTION);
        $section->jobIdsToCompare = [];
        $this->redrawControl('comparisonList');
    }

    /**
     * @admin-access-level read
     */
    public function handleAddToComparison($jobId)
    {
        $job = $this->retentionAnalysisJobsRepository->find($jobId);
        if (!$job) {
            throw new \Exception("Job with ID#{$jobId} was not found");
        }
        if ($job->state !== RetentionAnalysisJobsRepository::STATE_FINISHED) {
            throw new \Exception("Job with ID#{$jobId} is not finished yet");
        }

        $section = $this->getSession(self::SESSION_SECTION);
        $section->jobIdsToCompare = $section->jobIdsToCompare ?? [];
        if (!in_array($jobId, $section->jobIdsToCompare)) {
            $section->jobIdsToCompare[] = $jobId;
        }

        $this->redrawControl('comparisonList');
    }

    /**
     * @admin-access-level write
     */
    public function handleRerunJob($jobId)
    {
        $job = $this->retentionAnalysisJobsRepository->find($jobId);
        if (!$job) {
            throw new \Exception("Job with ID#{$jobId} was not found");
        }

        if (!in_array($job->state, [RetentionAnalysisJobsRepository::STATE_FINISHED, RetentionAnalysisJobsRepository::STATE_FAILED], true)) {
            throw new \Exception("Cannot rerun job ID#{$jobId} because it is not in FINISHED or FAILED state.");
        }

        $this->removeJobFromComparison($job->id);

        $this->retentionAnalysisJobsRepository->update($job, [
            'state' => RetentionAnalysisJobsRepository::STATE_CREATED,
            'started_at' => null,
            'finished_at' => null,
        ]);

        $this->hermesEmitter->emit(new HermesMessage('retention-analysis-job', [
            'id' => $job->id
        ]), HermesMessage::PRIORITY_LOW);

        $this->flashMessage($this->translator->translate('payments.admin.retention_analysis.job_was_rerun'));

        $this->redirect('this');
    }

    /**
     * @admin-access-level write
     */
    public function handleRemoveJob($jobId)
    {
        $job = $this->retentionAnalysisJobsRepository->find($jobId);
        if (!$job) {
            throw new \Exception("Job with ID#{$jobId} was not found");
        }

        if (!in_array($job->state, [RetentionAnalysisJobsRepository::STATE_FINISHED, RetentionAnalysisJobsRepository::STATE_FAILED], true)) {
            throw new \Exception("Cannot delete job ID#{$jobId} because it is not in FINISHED or FAILED state.");
        }

        $this->removeJobFromComparison($jobId);

        $this->retentionAnalysisJobsRepository->delete($job);
        $this->flashMessage($this->translator->translate('payments.admin.retention_analysis.job_removed'));

        $this->redirect('this');
    }

    private function removeJobFromComparison($jobId)
    {
        $section = $this->getSession(self::SESSION_SECTION);
        $section->jobIdsToCompare = $section->jobIdsToCompare ?? [];

        $foundIndex = array_search($jobId, $section->jobIdsToCompare, false);
        if ($foundIndex !== false) {
            unset($section->jobIdsToCompare[$foundIndex]);
        }
    }

    /**
     * @admin-access-level read
     */
    public function renderCompare()
    {
        $section = $this->getSession(self::SESSION_SECTION);
        if (!isset($section->jobIdsToCompare) || count($section->jobIdsToCompare) <= 1) {
            $this->redirect('default');
        }

        $jobsToCompare = $this->retentionAnalysisJobsRepository->getTable()->where(['id IN (?)' => $section->jobIdsToCompare])->fetchAll();

        $lowestLastPeriodNumber = null;

        $comparison = [];

        foreach ($jobsToCompare as $job) {
            $results = Json::decode($job->results, Json::FORCE_ARRAY);
            $retention = $results['retention'];
            ksort($retention);

            $periodNumberCounts = [];
            foreach ($retention as $yearMonth => $periods) {
                foreach ($periods as $periodNumber => $period) {
                    if (!array_key_exists($periodNumber, $periodNumberCounts)) {
                        $periodNumberCounts[$periodNumber] = [
                            'retention_count' => 0,
                            'users_count' => 0,
                        ];
                    }

                    $periodNumberCounts[$periodNumber]['retention_count'] += $period['count'];
                    $periodNumberCounts[$periodNumber]['users_count'] += $period['users_in_period'];
                }
            }
            $totalCount = $periodNumberCounts[0]['users_count'];

            $comparison[$job->id] = [
                'total_count' => $totalCount,
                'periods' => [],
            ];

            foreach ($periodNumberCounts as $periodNum => $values) {
                $ratio = (float) $values['retention_count'] / $values['users_count'];
                $values['color'] = 'churn-color-' . floor($ratio * 10) * 10;
                $values['percentage'] = number_format($ratio * 100, 1, '.', '') . '%';
                $comparison[$job->id]['periods'][$periodNum] = $values;
            }

            if ($lowestLastPeriodNumber) {
                $lowestLastPeriodNumber = min($lowestLastPeriodNumber, array_key_last($periodNumberCounts));
            } else {
                $lowestLastPeriodNumber = array_key_last($periodNumberCounts);
            }
        }
        $this->template->jobs = $jobsToCompare;
        $this->template->comparison = $comparison;
        $this->template->lowestLastPeriodNumber = $lowestLastPeriodNumber;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew()
    {
        if ($this->getParameter('submitted')) {
            // Increase time limit for calculations
            set_time_limit(120);
            $this->template->paymentCounts = $this->retentionAnalysis->precalculateMonthlyPaymentCounts($this->params);
        }
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($job)
    {
        $job = $this->retentionAnalysisJobsRepository->find($job);
        if (!$job) {
            throw new BadRequestException();
        }
        $this->template->job = $job;

        if ($job->state === RetentionAnalysisJobsRepository::STATE_FINISHED && $job->results) {
            $results = Json::decode($job->results, Json::FORCE_ARRAY);
            $retention = $results['retention'];

            ksort($retention);
            $colsCount = count($retention[array_key_first($retention)]) ?? 0;

            $tableRows = [];

            $allPeriodCounts = 0;
            $maxPeriodCount = 0;
            $periodNumberValues = [];

            foreach ($retention as $yearMonth => $result) {
                $tableRow = [];
                [$tableRow['year'], $tableRow['month']] =  explode('-', $yearMonth);
                $tableRow['fullPeriodCount'] = $fullPeriodCount = $result[array_key_first($result)]['count'] ?? 0;
                $allPeriodCounts += $fullPeriodCount;
                $maxPeriodCount = max($maxPeriodCount, $fullPeriodCount);
                $tableRow['periods'] = [];

                foreach ($result as $periodNumber => $period) {
                    $retentionCount = $period['count'];
                    $usersCount = $period['users_in_period'];
                    if (!array_key_exists($periodNumber, $periodNumberValues)) {
                        $periodNumberValues[$periodNumber] = [
                            'retention_count' => 0,
                            'users_count' => 0,
                        ];
                    }
                    $periodNumberValues[$periodNumber]['retention_count'] += $retentionCount;
                    $periodNumberValues[$periodNumber]['users_count'] += $usersCount;

                    $ratio = (float) $retentionCount/$period['users_in_period'];
                    $tableRow['periods'][] =  [
                        'retention_count' => $retentionCount,
                        'users_count' => $period['users_in_period'],
                        'color' => 'churn-color-' . floor($ratio * 10) * 10,
                        'percentage' =>  number_format($ratio * 100, 1, '.', '') . '%' . ($period['incomplete'] ?? false ? '*' : '')
                    ];
                }
                $tableRows[] = $tableRow;
            }

            $this->template->periodNumberCounts = [];
            foreach ($periodNumberValues as $values) {
                $ratio = (float) $values['retention_count'] / $values['users_count'];
                $values['color'] = 'churn-color-' . floor($ratio * 10) * 10;
                $values['percentage'] = number_format($ratio * 100, 1, '.', '') . '%';
                $this->template->periodNumberCounts[] = $values;
            }

            foreach ($tableRows as $i => $tableRow) {
                $ratio = $tableRow['fullPeriodCount'] / $maxPeriodCount;
                $tableRow['fullPeriodCount'] = [
                    'value' => $tableRow['fullPeriodCount'],
                    'color' => 'churn-color-' . floor($ratio * 10) * 10,
                ];

                $tableRows[$i] = $tableRow;
            }

            $this->template->allPeriodCounts = $allPeriodCounts;
            $this->template->colsCount = $colsCount;
            $this->template->tableRows = $tableRows;
        }
    }

    public function createComponentFilterForm(): Form
    {
        $form = $this->retentionAnalysisFilterFormFactory->create($this->params);
        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        return $form;
    }

    public function createComponentDisabledFilterForm(): Form
    {
        $job = $this->retentionAnalysisJobsRepository->find($this->params['job']);
        $inputParams = Json::decode($job->params, Json::FORCE_ARRAY);
        return $this->retentionAnalysisFilterFormFactory->create($inputParams, true);
    }

    public function createComponentScheduleComputationForm(): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapInlineRenderer());

        $form->addText('name', 'payments.admin.retention_analysis.analysis_name');
        $form->addHidden('jsonParams', Json::encode($this->params));

        $form->addSubmit('send', 'payments.admin.retention_analysis.schedule_computation')
            ->getControlPrototype()
            ->setName('button');

        $form->onSuccess[] = [$this, 'scheduleComputationSubmitted'];
        return $form;
    }

    public function scheduleComputationSubmitted($form, $values)
    {
        $params = array_filter(Json::decode($values['jsonParams'], Json::FORCE_ARRAY));
        unset($params['action'], $params['submitted']);
        $job = $this->retentionAnalysisJobsRepository->add($values['name'], Json::encode($params));
        $this->hermesEmitter->emit(new HermesMessage('retention-analysis-job', [
            'id' => $job->id
        ]), HermesMessage::PRIORITY_LOW);

        $this->flashMessage($this->translator->translate('payments.admin.retention_analysis.analysis_was_scheduled'));
        $this->redirect('default');
    }
}
