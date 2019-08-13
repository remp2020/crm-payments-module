<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Components\ChangePaymentStatusFactoryInterface;
use Crm\PaymentsModule\DataProvider\AdminFilterFormDataProviderInterface;
use Crm\PaymentsModule\Forms\PaymentFormFactory;
use Crm\PaymentsModule\PaymentsHistogramFactory;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class PaymentsAdminPresenter extends AdminPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

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
    public $payment_gateway;

    /** @persistent */
    public $subscription_type;

    /** @persistent */
    public $status;

    /** @persistent */
    public $donation;

    /** @persistent */
    public $month;

    /** @persistent */
    public $recurrent_charge = 'all';

    /** @persistent */
    public $products = [];

    /** @persistent */
    public $sales_funnel = [];

    public function startup()
    {
        parent::startup();
        $this->month = isset($this->params['month']) ? $this->params['month'] : '';
    }

    public function renderDefault()
    {
        $payments = $this->filteredPayments();
        $filteredCount = $payments->count('*');

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($filteredCount);
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;
        $this->template->filteredCount = $filteredCount;
        $this->template->payments = $payments->limit($paginator->getLength(), $paginator->getOffset());
        $this->template->totalPayments = $this->paymentsRepository->totalCount(true);
    }

    private function filteredPayments()
    {
        $recurrentChargeValues = [
            'all' => null,
            'recurrent' => true,
            'manual' => false,
        ];

        $payments = $this->paymentsRepository->all(
            $this->text,
            $this->payment_gateway,
            $this->subscription_type,
            $this->status,
            null,
            null,
            null,
            $this->donation,
            $recurrentChargeValues[$this->recurrent_charge] ?? null
        );

        $payments->order('created_at DESC')->order('id DESC');
        /** @var AdminFilterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.list_filter_form', AdminFilterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $payments = $provider->filter($payments, $this->request);
        }

        return $payments;
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);
        $form->addText('text', 'payments.admin.component.admin_filter_form.variable_symbol.label')
            ->setAttribute('autofocus');

        $paymentGateways = $this->paymentGatewaysRepository->all()->fetchPairs('id', 'name');
        $form->addSelect(
            'payment_gateway',
            'payments.admin.component.admin_filter_form.payment_gateway.label',
            $paymentGateways
        )->setPrompt('--');

        $statuses = $this->paymentsRepository->getStatusPairs();
        $form->addSelect(
            'status',
            'payments.admin.component.admin_filter_form.status.label',
            $statuses
        )->setPrompt('--');

        $donations = [
            true => $this->translator->translate('payments.admin.component.admin_filter_form.donation.with_donation'),
            false => $this->translator->translate('payments.admin.component.admin_filter_form.donation.without_donation'),
        ];
        $form->addSelect(
            'donation',
            'payments.admin.component.admin_filter_form.donation.label',
            $donations
        )->setPrompt('--');

        $form->addSelect(
            'recurrent_charge',
            'payments.admin.component.admin_filter_form.recurrent_charge.label',
            [
                'all' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.all'),
                'recurrent' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.recurrent'),
                'manual' => $this->translator->translate('payments.admin.component.admin_filter_form.recurrent_charge.manual'),
            ]
        );

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchPairs('id', 'name');
        $subscriptionType = $form->addSelect(
            'subscription_type',
            'payments.admin.component.admin_filter_form.subscription_type.label',
            $subscriptionTypes
        )->setPrompt('--');
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        /** @var AdminFilterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.list_filter_form', AdminFilterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'request' => $this->request]);
        }

        $form->addSubmit('send', 'payments.admin.component.admin_filter_form.filter.label')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.component.admin_filter_form.filter.label'));
        $presenter = $this;

        $form->addSubmit('cancel', 'payments.admin.component.admin_filter_form.filter.cancel')->onClick[] = function () use ($presenter) {
            $presenter->redirect('PaymentsAdmin:Default', ['text' => '']);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'text' => $this->text,
            'payment_gateway' => $this->payment_gateway,
            'subscription_type' => $this->subscription_type,
            'status' => $this->status,
            'donation' => $this->donation,
            'recurrent_charge' => $this->recurrent_charge,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect($this->action, array_filter((array)$values));
    }

    public function actionChangeStatus()
    {
        $payment = $this->paymentsRepository->find($this->params['payment']);
        $this->paymentsRepository->updateStatus($payment, $this->params['status']);
        $this->flashMessage($this->translator->translate('payments.admin.payments.updated'));
        $this->redirect(':Users:UsersAdmin:Show', $payment->user_id);
    }

    public function renderExport()
    {
        $this->getHttpResponse()->addHeader('Content-Type', 'application/csv');
        $this->getHttpResponse()->addHeader('Content-Disposition', 'attachment; filename=export.csv');
        $this->template->payments = $this->filteredPayments();
    }

    public function renderEdit($id, $userId)
    {
        $payment = $this->paymentsRepository->find($id);
        if (!$payment) {
            throw new BadRequestException();
        }
        $this->template->payment = $payment;
        $this->template->user = $payment->user;
    }

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
