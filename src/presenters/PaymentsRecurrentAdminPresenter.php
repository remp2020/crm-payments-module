<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\Graphs\SmallBarGraphControlFactoryInterface;
use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Components\ComfortPayStatus;
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

    public function startup()
    {
        parent::startup();
        $this->problem = isset($this->params['problem']) ? $this->params['problem'] : null;
        $this->subscription_type = isset($this->params['subscription_type']) ? $this->params['subscription_type'] : null;
        $this->status = isset($this->params['status']) ? $this->params['status'] : null;
    }

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

        $this->template->addFilter('recurrentStatus', function ($status) {
            $data = ComfortPayStatus::getStatusHtml($status);
            return '<span class="label label-' . $data['label'] . '">' . $data['text'] . '</span>';
        });
    }

    public function createComponentAdminFilterForm()
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer());
        $statuses = $this->recurrentPaymentsRepository->getStatusPairs();
        $statuses[0] = '--';
        $form->addSelect('status', 'Stav platby', $statuses);
        $form->addCheckbox('problem', 'Iba Neuspesna platba');

        $subscriptionTypes = $this->subscriptionTypesRepository->getAllActive()->fetchPairs('id', 'name');
        $subscriptionTypes[0] = '--';
        $form->addselect('subscription_type', 'Typ predplatného', $subscriptionTypes);

        $form->addSubmit('send', 'Filter')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> Filter');
        $presenter = $this;
        $form->addSubmit('cancel', 'Zruš filter')->onClick[] = function () use ($presenter) {
            $presenter->redirect('default', ['text' => '']);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'subscription_type' => isset($_GET['subscription_type']) ? $_GET['subscription_type'] : 0,
            'status' => isset($_GET['status']) ? $_GET['status'] : 0,
            'problem' => isset($_GET['problem']) ? $_GET['problem'] : 0
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

    public function renderEdit($id)
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->find($id);
        $this->template->recurrentPayment = $recurrentPayment;
    }

    protected function createComponentRecurrentPaymentForm()
    {
        $form = $this->recurrentPaymentFormFactory->create($this->params['id']);
        $this->recurrentPaymentFormFactory->onUpdate = function ($form, $recurrentPayment) {
            $this->flashMessage('Rekurentný profil bol upravený');
            $this->redirect(':Users:UsersAdmin:Show', $recurrentPayment->user_id);
        };
        return $form;
    }

    protected function createComponentDuplicateRecurrentPayments(DuplicateRecurrentPaymentsControlFactoryInterface $factory)
    {
        return $factory->create();
    }
}
