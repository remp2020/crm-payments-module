<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\RetentionAnalysisDataProviderInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RetentionAnalysisFilterFormFactory
{
    private $translator;

    private $paymentsRepository;

    private $dataProviderManager;

    private $segmentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        DataProviderManager $dataProviderManager,
        SegmentsRepository $segmentsRepository,
        ITranslator $translator
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
        $this->dataProviderManager = $dataProviderManager;
        $this->segmentsRepository = $segmentsRepository;
    }

    public function create(array $inputParams, bool $disabled = false): Form
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
            ->setAttribute('class', 'flatpickr')
            ->setOption('description', 'payments.admin.retention_analysis.fields.min_date_of_payment_desc')
            ->setDisabled($disabled);

        if (!$disabled) {
            $dateHtmlId = $date->getHtmlId();
            $form->addButton('clear_date', 'payments.admin.retention_analysis.fields.clear_date')
                ->setAttribute('class', 'btn btn-default')
                ->setHtmlAttribute('onclick', "document.getElementById('{$dateHtmlId}')._flatpickr.clear()");
        }

        $form->addSelect('previous_user_subscriptions', 'payments.admin.retention_analysis.fields.previous_user_subscriptions', [
            'without_previous_subscription' => $this->translator->translate('payments.admin.retention_analysis.fields.without_previous_subscription'),
            'with_previous_subscription_at_least_one_paid' => $this->translator->translate('payments.admin.retention_analysis.fields.with_previous_subscription_at_least_one_paid'),
            'with_previous_subscription_all_unpaid' => $this->translator->translate('payments.admin.retention_analysis.fields.with_previous_subscription_all_unpaid'),
        ])
            ->setPrompt('--')
            ->setDisabled($disabled);

        $form->addSelect('segment_code', 'payments.admin.retention_analysis.fields.segment', $this->segmentsRepository->all()->fetchPairs('code', 'name'))
            ->setDisabled($disabled)
            ->setPrompt('--')
            ->getControlPrototype()->addAttributes(['class' => 'select2']);

        /** @var RetentionAnalysisDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.retention_analysis', RetentionAnalysisDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'inputParams' => $inputParams] + ($disabled ? ['disable' => true] : []));
        }

        $form->addHidden('submitted', 1);

        if (!$disabled) {
            $form->addSubmit('send', 'system.filter')
                ->getControlPrototype()
                ->setName('button')
                ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.retention_analysis.preview_run'));
        }

        $form->setDefaults($inputParams);
        return $form;
    }
}
