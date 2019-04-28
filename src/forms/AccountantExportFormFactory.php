<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AccountantExportFormFactory
{
    private $paymentGatewaysRepository;

    private $translator;

    private $paymentsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        ITranslator $translator
    ) {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->translator = $translator;
    }

    public function create(...$filteredFields)
    {

        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $paymentGateways = $this->paymentGatewaysRepository->all()->fetchPairs('id', 'name');
        $form->addSelect(
            'payment_gateway',
            'payments.form.accountant_export.payment_gateway.label',
            $paymentGateways
        )
            ->setPrompt('--');

        $statuses = $this->paymentsRepository->getStatusPairs();
        $form->addSelect(
            'status',
            'payments.form.accountant_export.status.label',
            $statuses
        )
            ->setPrompt('--');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        $subscriptionTypesArray = [];

        /** @var ActiveRow $subscriptionType */
        foreach ($subscriptionTypes as $subscriptionType) {
            $subscriptionTypesArray[$subscriptionType->id] = $subscriptionType->length . ' - ' . $subscriptionType->price . ' - ' . $subscriptionType->name;
        }

        $form->addSelect(
            'subscription_type',
            'payments.form.accountant_export.subscription_type.label',
            $subscriptionTypesArray
        )
            ->setPrompt('--');

        $last = new \DateTime();
        $last->setDate(2014, 12, 1);

        $dates = [];
        $now = new \DateTime();
        while ($now > $last) {
            $dates[$last->format('Y-m')] = $last->format('Y-m');
            $last->modify('+1 month');
        }

        $form->addSelect(
            'month',
            'payments.form.accountant_export.month.label',
            $dates
        );

        $form->setDefaults([
            'status' => PaymentsRepository::STATUS_PAID,
            'month' => DateTime::from('-1 month')->format('Y-m'),
        ]);

        if (!empty($filteredFields)) {
            foreach ($form->getControls() as $key => $control) {
                if (!in_array($key, $filteredFields)) {
                    unset($form[$key]);
                }
            }
        }

        $export = $form->addSubmit('send', 'payments.form.accountant_export.export');
        $export->getControlPrototype()->setName('button')->setHtml('<i class="fa fa-external-link"></i>&nbsp;' . $this->translator->translate('payments.form.accountant_export.export'));

        return $form;
    }
}
