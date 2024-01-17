<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Retention\RetentionAnalysis;
use Crm\SegmentModule\Repositories\SegmentsRepository;
use Crm\UsersModule\Repository\UsersRepository;
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
        private Translator $translator
    ) {
    }

    public function create(array $inputParams, bool $disabled = false, int $version = RetentionAnalysis::VERSION): Form
    {
        $form = new Form();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $earliestPayment = $this->paymentsRepository->getTable()
            ->select('MIN(paid_at) AS paid_at')
            ->where('paid_at IS NOT NULL')
            ->where('subscription_id IS NOT NULL')
            ->fetch();

        if (!$earliestPayment) {
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
}
