<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AccountantExportFormFactory
{
    private $paymentGatewaysRepository;

    private $paymentsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        PaymentsRepository $paymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function create(...$filteredFields)
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());

        $paymentGateways = $this->paymentGatewaysRepository->getAllActive()->fetchPairs('id', 'name');
        $paymentGateways[0] = '--';
        $form->addSelect('payment_gateway', 'Platobna brana', $paymentGateways);

        $statuses = $this->paymentsRepository->getStatusPairs();
        $statuses[0] = '--';
        $form->addSelect('status', 'Stav platby', $statuses);

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        $subscriptionTypesArray = [];

        /** @var ActiveRow $subscriptionType */
        foreach ($subscriptionTypes as $subscriptionType) {
            $subscriptionTypesArray[$subscriptionType->id] = $subscriptionType->length . ' - ' . $subscriptionType->price . ' - ' . $subscriptionType->name;
        }

        $subscriptionTypesArray[0] = '--';
        $form->addSelect('subscription_type', 'Typ predpatneho', $subscriptionTypesArray);

        $last = new \DateTime();
        $last->setDate(2014, 12, 1);

        $dates = [];
        $now = new \DateTime();
        while ($now > $last) {
            $dates[$last->format('Y-m')] = $last->format('Y-m');
            $last->modify('+1 month');
        }

        $form->addSelect('month', 'Mesiac', $dates);

        $form->setDefaults([
            'month' => isset($_GET['month']) ? $_GET['month'] : $now->format('Y-m'),
            'payment_gateway' => isset($_GET['payment_gateway']) ? $_GET['payment_gateway'] : 0,
            'subscription_type' => isset($_GET['subscription_type']) ? $_GET['subscription_type'] : 0,
            'status' => $_GET['status'] ?? 'paid',
        ]);

        if (!empty($filteredFields)) {
            foreach ($form->getControls() as $key => $control) {
                if (!in_array($key, $filteredFields)) {
                    unset($form[$key]);
                }
            }
        }

        $export = $form->addSubmit('send', 'Export');
        $export->getControlPrototype()->setName('button')->setHtml('<i class="fa fa-external-link"></i> Export');

        return $form;
    }
}
