<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\SmallBarGraph\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Components\PreviousNextPaginator\PreviousNextPaginator;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphData;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Components\DuplicateRecurrentPayments\DuplicateRecurrentPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\PaymentsModule\Forms\RecurrentPaymentFormFactory;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Application\Attributes\Persistent;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;
use Tracy\Debugger;

class PaymentsRecurrentAdminPresenter extends AdminPresenter
{
    #[Persistent]
    public $subscription_type;

    #[Persistent]
    public $status;

    #[Persistent]
    public $problem;

    #[Persistent]
    public $cid;

    public function __construct(
        private readonly GraphData $graphData,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly RecurrentPaymentFormFactory $recurrentPaymentFormFactory,
        private readonly UserDateHelper $userDateHelper,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
    ) {
        parent::__construct();
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $recurrentPayments = $this->recurrentPaymentsRepository->all(
            $this->problem,
            $this->subscription_type,
            $this->status,
            $this->cid,
        );

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $recurrentPayments = $recurrentPayments->limit(
            $paginator->getLength(),
            $paginator->getOffset(),
        )->fetchAll();
        $pnp->setActualItemCount(count($recurrentPayments));

        $this->template->recurrentPayments = $recurrentPayments;
        $this->template->totalRecurrentPayments = $this->recurrentPaymentsRepository->totalCount(true);
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('cid', 'payments.admin.payments_recurrent.admin_filter_form.cid.label');

        $statuses = $this->recurrentPaymentsRepository->getStatusPairs();
        $form->addSelect('status', 'payments.admin.payments_recurrent.admin_filter_form.status.label', $statuses)
            ->setPrompt('--');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        $form->addselect(
            'subscription_type',
            'payments.admin.payments_recurrent.admin_filter_form.subscription_type.label',
            $this->subscriptionTypesSelectItemsBuilder->buildSimple($subscriptionTypes),
        )->setPrompt('--');

        $form->addCheckbox('problem', 'payments.admin.payments_recurrent.admin_filter_form.problem.label');

        $form->addSubmit('send', 'payments.admin.payments_recurrent.admin_filter_form.send')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.payments_recurrent.admin_filter_form.send'));

        $form->addSubmit('cancel', 'payments.admin.payments_recurrent.admin_filter_form.cancel')->onClick[] = function () use ($form) {
            $emptyDefaults = array_fill_keys(array_keys((array) $form->getComponents()), null);
            $this->redirect('default', $emptyDefaults);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'subscription_type' => $this->subscription_type,
            'status' => $this->status,
            'problem' => $this->problem,
            'cid' => $this->cid,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('Default', [
            'problem' => $values['problem'],
            'subscription_type' => $values['subscription_type'],
            'status' => $values['status'],
            'cid' => $values['cid'],
        ]);
    }

    protected function createComponentFormRecurrentPaymentsSmallBarGraph(SmallBarGraphControlFactoryInterface $factory)
    {
        return $this->generateSmallBarGraphComponent(
            RecurrentPaymentStateEnum::Active->value,
            'Active',
            $factory,
        );
    }

    private function generateSmallBarGraphComponent($status, $title, SmallBarGraphControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('recurrent_payments')
            ->setWhere("AND recurrent_payments.state = '$status'"));

        $this->graphData->clear();
        $this->graphData->addGraphDataItem($graphDataItem);
        $this->graphData->setScaleRange('day')
            ->setStart('-40 days');

        $control = $factory->create();
        $control->setGraphTitle($title)
            ->addSerie($this->graphData->getData());

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

    /**
     * @admin-access-level write
     */
    public function actionReactivateFailedPayment(int $recurrentPaymentId)
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);

        try {
            $recurrentPaymentReactivated = $this->recurrentPaymentsRepository->reactivateSystemStopped($recurrentPayment);
        } catch (\Exception $exception) {
            $this->flashMessage($this->translator->translate('payments.admin.payments_recurrent.reactivate_failed_payment.incorrect_state'));
            Debugger::log($exception, Debugger::EXCEPTION);
            $this->redirect(':Users:UsersAdmin:Show', $recurrentPayment->user_id);
        }

        $this->flashMessage($this->translator->translate(
            'payments.admin.payments_recurrent.reactivate_failed_payment.success',
            ['next_charge_datetime' => $this->userDateHelper->process($recurrentPaymentReactivated->charge_at)],
        ));
        $this->redirect(':Users:UsersAdmin:Show', $recurrentPaymentReactivated->user_id);
    }
}
