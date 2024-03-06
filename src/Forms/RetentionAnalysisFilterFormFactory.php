<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProviders\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Models\Retention\RetentionAnalysis;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeTagsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RetentionAnalysisFilterFormFactory
{
    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private DataProviderManager $dataProviderManager,
        private SegmentsRepository $segmentsRepository,
        private UsersRepository $usersRepository,
        private Translator $translator,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypeTagsRepository $subscriptionTypeTagsRepository,
    ) {
    }

    public function create(array $inputParams, bool $disabled = false, int $version = RetentionAnalysis::VERSION): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $isAnyPayment = $this->paymentsRepository->getTable()
            ->where('paid_at IS NOT NULL')
            ->where('subscription_id IS NOT NULL')
            ->limit(1)
            ->fetch();

        if (!$isAnyPayment) {
            throw new \Exception('No payment, cannot render form');
        }

        $date = $form->addText('min_date_of_payment', 'payments.admin.retention_analysis.fields.min_date_of_payment')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setOption('description', 'payments.admin.retention_analysis.fields.min_date_of_payment_desc')
            ->setDisabled($disabled);

        if (!$disabled) {
            $dateHtmlId = $date->getHtmlId();
            $form->addButton('clear_date', 'payments.admin.retention_analysis.fields.clear_date')
                ->setHtmlAttribute('class', 'btn btn-default')
                ->setHtmlAttribute('onclick', "document.getElementById('{$dateHtmlId}')._flatpickr.clear()");
        }

        $form->addInteger('zero_period_length', 'payments.admin.retention_analysis.fields.zero_period_length')
            ->setRequired()
            ->setDisabled($disabled)
            ->addRule(Form::MIN, 'payments.admin.retention_analysis.fields.period_length_invalid', 1)
            ->addRule(Form::MAX, 'payments.admin.retention_analysis.fields.period_length_invalid', 10000);

        $form->addInteger('period_length', 'payments.admin.retention_analysis.fields.period_length')
            ->setRequired()
            ->setDisabled($disabled)
            ->addRule(Form::MIN, 'payments.admin.retention_analysis.fields.period_length_invalid', 1)
            ->addRule(Form::MAX, 'payments.admin.retention_analysis.fields.period_length_invalid', 10000);

        $form->addSelect('partition', 'payments.admin.retention_analysis.fields.partition', $this->getAvailablePartitionOptions())
            ->setDisabled($disabled);

        $form->addSelect('previous_user_subscriptions', 'payments.admin.retention_analysis.fields.previous_user_subscriptions', [
            'without_previous_subscription' => $this->translator->translate('payments.admin.retention_analysis.fields.without_previous_subscription'),
            'with_previous_subscription_at_least_one_paid' => $this->translator->translate('payments.admin.retention_analysis.fields.with_previous_subscription_at_least_one_paid'),
            'with_previous_subscription_all_unpaid' => $this->translator->translate('payments.admin.retention_analysis.fields.with_previous_subscription_all_unpaid'),
        ])
            ->setPrompt('--')
            ->setDisabled($disabled);

        $form->addSelect('segment_code', 'payments.admin.retention_analysis.fields.segment', $this->segmentsRepository->all()->fetchPairs('code', 'name'))
            ->setPrompt('--')
            ->setDisabled($disabled)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addSelect('user_source', 'payments.admin.retention_analysis.fields.user_source', $this->usersRepository->getUserSources())
            ->setPrompt('--')
            ->setDisabled($disabled)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $subscriptionTypes = [];
        foreach ($this->subscriptionTypesRepository->all() as $row) {
            $subscriptionTypes[$row->id] = "$row->code <small>({$row->id})</small>";
        }
        $form->addMultiSelect('subscription_type', 'payments.admin.retention_analysis.fields.subscription_type', $subscriptionTypes)
            ->setDisabled($disabled)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        $tags = $this->subscriptionTypeTagsRepository->tagsSortedByOccurrences();
        $form->addMultiSelect('subscription_type_tag', 'payments.admin.retention_analysis.fields.subscription_type_tag', $tags)
            ->setDisabled($disabled)
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        /** @var RetentionAnalysisDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.retention_analysis', RetentionAnalysisDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'inputParams' => $inputParams] + ($disabled ? ['disable' => true] : []));
        }

        $form->addHidden('submitted', 1);

        if (!$disabled) {
            $form->addSubmit('send')
                ->getControlPrototype()
                ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.retention_analysis.preview_run'))
                ->setName('button');
        }

        $defaultPeriodLength = 28;
        if ($version <= 1) {
            $defaultPeriodLength = 31;
        }

        $inputParams['zero_period_length'] = $inputParams['zero_period_length'] ?? $defaultPeriodLength;
        $inputParams['period_length'] = $inputParams['period_length'] ?? $defaultPeriodLength;

        $form->setDefaults($inputParams);
        return $form;
    }

    private function getAvailablePartitionOptions(): array
    {
        $options = [];
        foreach (RetentionAnalysis::PARTITION_OPTIONS as $partitionOption) {
            $options[$partitionOption] = 'payments.admin.retention_analysis.partition.' . $partitionOption;
        }

        return $options;
    }
}
