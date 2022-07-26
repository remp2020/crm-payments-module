<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\AdminFilterFormData;
use Crm\PaymentsModule\Components\ChangePaymentStatusFactoryInterface;
use Crm\PaymentsModule\DataProvider\AdminFilterFormDataProviderInterface;
use Crm\PaymentsModule\Forms\PaymentFormFactory;
use Crm\PaymentsModule\PaymentsHistogramFactory;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapRenderer;
use Tomaj\Hermes\Emitter;

class PaymentsAdminPresenter extends AdminPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var PaymentItemMetaRepository @inject */
    public $paymentItemMetaRepository;

    /** @var PaymentGatewaysRepository @inject */
    public $paymentGatewaysRepository;

    /** @var SubscriptionTypesRepository @inject */
    public $subscriptionTypesRepository;

    /** @var UsersRepository @inject */
    public $usersRepository;

    /** @var PaymentFormFactory @inject */
    public $factory;

    /** @var DataProviderManager @inject */
    public $dataProviderManager;

    /** @var PaymentsHistogramFactory @inject */
    public $paymentsHistogramFactory;

    /** @persistent */
    public $month;

    /** @persistent */
    public $formData = [];

    private $adminFilterFormData;

    private $hermesEmitter;


    public function __construct(
        AdminFilterFormData $adminFilterFormData,
        Emitter $hermesEmitter
    ) {
        parent::__construct();
        $this->adminFilterFormData = $adminFilterFormData;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function startup()
    {
        parent::startup();
        $this->month = $this->params['month'] ?? '';
        $this->adminFilterFormData->parse($this->formData);
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $payments = $this->adminFilterFormData->filteredPayments()
            ->order('created_at DESC')
            ->order('id DESC');

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $payments = $payments->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($payments));

        $this->template->payments = $payments;
        $this->template->totalPayments = $this->paymentsRepository->totalCount(true);
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $mainGroup = $form->addGroup('main')->setOption('label', null);
        $collapseGroup = $form->addGroup('collapse', false)
            ->setOption('container', 'div class="collapse"')
            ->setOption('label', null)
            ->setOption('id', 'formCollapse');
        $buttonGroup = $form->addGroup('button', false)->setOption('label', null);

        $form->addText('text', 'payments.admin.component.admin_filter_form.variable_symbol.label')
            ->setAttribute('autofocus');

        $paymentGateways = $this->paymentGatewaysRepository->all()->fetchPairs('id', 'name');
        $paymentGateway = $form->addSelect(
            'payment_gateway',
            'payments.admin.component.admin_filter_form.payment_gateway.label',
            $paymentGateways
        )->setPrompt('--');
        $paymentGateway->getControlPrototype()->addAttributes(['class' => 'select2']);

        $statuses = $this->paymentsRepository->getStatusPairs();
        $status = $form->addSelect(
            'status',
            'payments.admin.component.admin_filter_form.status.label',
            $statuses
        )->setPrompt('--');
        $status->getControlPrototype()->addAttributes(['class' => 'select2']);

        $recurrentCharge = $form->addSelect(
            'recurrent_charge',
            'payments.admin.component.admin_filter_form.recurrent_charge.label',
            [
                'all' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.all'),
                'recurrent' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.recurrent'),
                'manual' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.manual'),
            ]
        );
        $recurrentCharge->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->setCurrentGroup($collapseGroup);

        $form->addText('paid_at_from', 'payments.admin.component.admin_filter_form.paid_at_from.label')
            ->setAttribute('placeholder', 'payments.admin.component.admin_filter_form.paid_at_from.placeholder')
            ->setAttribute('class', 'flatpickr')
            ->setAttribute('flatpickr_datetime', "1");

        $form->addText('paid_at_to', 'payments.admin.component.admin_filter_form.paid_at_to.label')
            ->setAttribute('placeholder', 'payments.admin.component.admin_filter_form.paid_at_to.placeholder')
            ->setAttribute('class', 'flatpickr')
            ->setAttribute('flatpickr_datetime', "1");

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchPairs('id', 'name');
        $subscriptionType = $form->addSelect(
            'subscription_type',
            'payments.admin.component.admin_filter_form.subscription_type.label',
            $subscriptionTypes
        )->setPrompt('--');
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addText('referer', 'payments.admin.component.admin_filter_form.referer.label');

        $donations = [
            true => $this->translator->translate('payments.admin.component.admin_filter_form.donation.with_donation'),
            false => $this->translator->translate('payments.admin.component.admin_filter_form.donation.without_donation'),
        ];
        $donation = $form->addSelect(
            'donation',
            'payments.admin.component.admin_filter_form.donation.label',
            $donations
        )->setPrompt('--');
        $donation->getControlPrototype()->addAttributes(['class' => 'select2']);

        /** @var AdminFilterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.payments_filter_form', AdminFilterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'formData' => $this->formData]);
        }

        $form->setCurrentGroup($buttonGroup);

        $form->addSubmit('send', 'payments.admin.component.admin_filter_form.filter.send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.component.admin_filter_form.filter.send'));
        $presenter = $this;

        $form->addSubmit('cancel', 'payments.admin.component.admin_filter_form.filter.cancel')->onClick[] = function () use ($presenter, $form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $presenter->redirect('PaymentsAdmin:Default', ['formData' => $emptyDefaults]);
        };

        $form->addButton('more')
            ->setHtmlAttribute('data-toggle', 'collapse')
            ->setHtmlAttribute('data-target', '#formCollapse')
            ->setAttribute('class', 'btn btn-info')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-caret-down"></i> ' . $this->translator->translate('payments.admin.component.admin_filter_form.filter.more'));

        $form->onSuccess[] = [$this, 'adminFilterSubmitted'];
        $form->setDefaults($this->adminFilterFormData->getFormValues());
        return $form;
    }

    public function adminFilterSubmitted($form, $values)
    {
        $this->redirect($this->action, ['formData' => array_map(function ($item) {
            return $item ?: null;
        }, (array)$values)]);
    }

    /**
     * @admin-access-level write
     */
    public function actionChangeStatus()
    {
        $payment = $this->paymentsRepository->find($this->params['payment']);
        if ($this->params['status'] === PaymentsRepository::STATUS_REFUND) {
            $this->paymentsRepository->updateStatus($payment, $this->params['status'], true);
        } else {
            $this->paymentsRepository->updateStatus($payment, $this->params['status']);
        }
        $this->flashMessage($this->translator->translate('payments.admin.payments.updated'));
        $this->redirect(':Users:UsersAdmin:Show', $payment->user_id);
    }

    /**
     * @admin-access-level write
     */
    public function handleExportPayments()
    {
        $this->hermesEmitter->emit(new HermesMessage('export-payments', [
            'form_data' => $this->formData,
            'user_id' => $this->user->getId()
        ]), HermesMessage::PRIORITY_LOW);

        $this->flashMessage($this->translator->translate('payments.admin.payments.export.exported'));
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id, $userId)
    {
        $payment = $this->paymentsRepository->find($id);
        if (!$payment) {
            throw new BadRequestException();
        }
        $this->template->payment = $payment;
        $this->template->user = $payment->user;

        $allowEditPaymentItems = true;

        if ($payment->status !== 'form') {
            $allowEditPaymentItems = false;
        }
        if ($allowEditPaymentItems) {
            foreach ($payment->related('payment_items')->fetchAll() as $item) {
                if ($item->type !== SubscriptionTypePaymentItem::TYPE) {
                    $allowEditPaymentItems = false;
                    break;
                }

                // TODO move to widget / dataprovider
                if ($item->type === SubscriptionTypePaymentItem::TYPE) {
                    if ($this->paymentItemMetaRepository->findByPaymentItemAndKey($item, 'revenue')->count('*')) {
                        $allowEditPaymentItems = false;
                        $this->flashMessage($this->translator->translate('payments.form.payment.items_no_editable'), 'warning');
                        break;
                    }
                }
            }
        }

        $this->template->allowEditPaymentItems = $allowEditPaymentItems;
    }

    /**
     * @admin-access-level write
     */
    public function renderNew($userId)
    {
        $user = $this->usersRepository->find($userId);
        if (!$user) {
            throw new BadRequestException();
        }
        $this->template->user = $user;
    }

    public function createComponentPaymentForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
            $user = $this->paymentsRepository->find($id)->user;
        } else {
            $user = $this->usersRepository->find($this->params['userId']);
            if (!$user) {
                throw new BadRequestException();
            }
        }

        $form = $this->factory->create($id, $user);
        $this->factory->onSave = function ($form, $payment) {
            $this->flashMessage($this->translator->translate('payments.admin.payments.created'));
            $this->redirect(':Users:UsersAdmin:Show', $payment->user->id);
        };
        $this->factory->onUpdate = function ($form, $payment) {
            $this->flashMessage($this->translator->translate('payments.admin.payments.updated'));
            $this->redirect(':Users:UsersAdmin:Show', $payment->user->id);
        };
        return $form;
    }

    protected function createComponentFormPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(PaymentsRepository::STATUS_FORM, 'Form', $factory);
    }

    protected function createComponentPaidPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(PaymentsRepository::STATUS_PAID, 'Paid', $factory);
    }

    protected function createComponentFailPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(PaymentsRepository::STATUS_FAIL, 'Fail', $factory);
    }

    protected function createComponentTimeoutPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(PaymentsRepository::STATUS_TIMEOUT, 'Timeout', $factory);
    }

    protected function createComponentRefundedPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(PaymentsRepository::STATUS_REFUND, 'Refunded', $factory);
    }

    private function generateSmallBarGraphComponent($status, $title, SmallBarGraphControlFactoryInterface $factory)
    {
        $data = $this->paymentsHistogramFactory->paymentsLastMonthDailyHistogram($status);

        $control = $factory->create();
        $control->setGraphTitle($title)->addSerie($data);

        return $control;
    }

    protected function createComponentChangePaymentStatus(ChangePaymentStatusFactoryInterface $factory)
    {
        return $factory->create();
    }
}
