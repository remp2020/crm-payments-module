<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Components\DuplicateRecurrentPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Forms\RecurrentPaymentFormFactory;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class PaymentsRecurrentAdminPresenter extends AdminPresenter
{
    /** @var SubscriptionTypesRepository @inject */
    public $subscriptionTypesRepository;

    /** @var  RecurrentPaymentsRepository @inject */
    public $recurrentPaymentsRepository;

    /** @var  RecurrentPaymentFormFactory @inject */
    public $recurrentPaymentFormFactory;

    /** @persistent */
    public $subscription_type;

    /** @persistent */
    public $status;

    /** @persistent */
    public $problem;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $recurrentPayments = $this->recurrentPaymentsRepository->all(
            $this->problem,
            $this->subscription_type,
            $this->status
        );
        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($recurrentPayments->count('*'));
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;
        $this->template->recurrentPayments = $recurrentPayments->limit(
            $paginator->getLength(),
            $paginator->getOffset()
        );
        $this->template->totalRecurrentPayments = $this->recurrentPaymentsRepository->totalCount();
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $statuses = $this->recurrentPaymentsRepository->getStatusPairs();
        $form->addSelect('status', 'payments.admin.payments_recurrent.admin_filter_form.status.label', $statuses)
            ->setPrompt('--');
        $form->addCheckbox('problem', 'payments.admin.payments_recurrent.admin_filter_form.problem.label');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchPairs('id', 'name');
        $form->addselect('subscription_type', 'payments.admin.payments_recurrent.admin_filter_form.subscription_type.label', $subscriptionTypes)
            ->setPrompt('--');

        $form->addSubmit('send', 'payments.admin.payments_recurrent.admin_filter_form.send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.payments_recurrent.admin_filter_form.send'));
        $presenter = $this;
        $form->addSubmit('cancel', 'payments.admin.payments_recurrent.admin_filter_form.cancel')->onClick[] = function () use ($presenter) {
            $presenter->redirect('default', ['text' => '']);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'subscription_type' => $this->subscription_type,
            'status' => $this->status,
            'problem' => $this->problem,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('Default', [
            'problem' => $values['problem'],
            'subscription_type' => $values['subscription_type'],
            'status' => $values['status'],
        ]);
    }

    protected function createComponentFormRecurrentPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent('active', 'Form', $factory);
    }

    private function generateSmallBarGraphComponent($status, $title, SmallBarGraphControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('recurrent_payments')
            ->setWhere("AND recurrent_payments.status = '$status'"));

        $graphData = $this->context->getService('graph_data');
        $graphData->addGraphDataItem($graphDataItem);
        $graphData->setScaleRange('day')
            ->setStart('-40 days');

        $control = $factory->create();
        $control->setGraphTitle($title)
            ->addSerie($graphData->getData());

        return $control;
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->find($id);
        $this->template->recurrentPayment = $recurrentPayment;
    }

    protected function createComponentRecurrentPaymentForm()
    {
        $form = $this->recurrentPaymentFormFactory->create($this->params['id']);
        $this->recurrentPaymentFormFactory->onUpdate = function ($form, $recurrentPayment) {
            $this->flashMessage($this->translator->translate('payments.admin.payments_recurrent.updated'));
            $this->redirect(':Users:UsersAdmin:Show', $recurrentPayment->user_id);
        };
        return $form;
    }

    protected function createComponentDuplicateRecurrentPayments(DuplicateRecurrentPaymentsControlFactoryInterface $factory)
    {
        return $factory->create();
    }

    /**
     * @admin-access-level read
     */
    public function renderDuplicates()
    {
    }
}
