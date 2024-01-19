<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroup\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Models\Graphs\Criteria;
use Crm\ApplicationModule\Models\Graphs\GraphDataItem;
use Crm\PaymentsModule\Components\LastPayments\LastPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Forms\PaymentGatewayFormFactory;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;

class PaymentGatewaysAdminPresenter extends AdminPresenter
{
    #[Inject]
    public PaymentGatewaysRepository $paymentGatewaysRepository;

    #[Inject]
    public PaymentGatewayFormFactory $factory;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $paymentGateways = $this->filteredGateways();
        $this->template->paymentGateways = $paymentGateways;
        $this->template->totalPaymentGateways = $this->paymentGatewaysRepository->totalCount();
    }

    private function filteredGateways()
    {
        return $this->paymentGatewaysRepository->all()->order('sorting ASC');
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($id);
        if (!$paymentGateway) {
            throw new BadRequestException();
        }
        $this->template->type = $paymentGateway;
    }

    /**
     * @admin-access-level write
     */
    public function renderEdit($id)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($id);
        if (!$paymentGateway) {
            throw new BadRequestException();
        }
        $this->template->type = $paymentGateway;
    }

    public function createComponentPaymentGatewayForm()
    {
        $id = null;
        if (isset($this->params['id'])) {
            $id = $this->params['id'];
        }

        $form = $this->factory->create($id);
        $this->factory->onUpdate = function ($form, $paymentGateway) {
            $this->flashMessage($this->translator->translate('payments.admin.payment_gateways.updated'));
            $this->redirect('PaymentGatewaysAdmin:Show', $paymentGateway->id);
        };
        return $form;
    }

    public function createComponentLastPayments(LastPaymentsControlFactoryInterface $factory)
    {
        $control = $factory->create();
        $control->setPaymentGatewayId($this->params['id']);
        $control->setLimit(100);
        return $control;
    }

    protected function createComponentPaymentGatewayGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $paymentStatusCompleted = "'" . implode("', '", [
            PaymentsRepository::STATUS_PAID,
            PaymentsRepository::STATUS_IMPORTED,
            PaymentsRepository::STATUS_PREPAID
            ]) . "'";
        $graphDataItem1 = new GraphDataItem();
        $graphDataItem1->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('created_at')
            ->setWhere("AND status IN (" . $paymentStatusCompleted . ") AND payment_gateway_id=" . intval($this->params['id']))
            ->setValueField('COUNT(*)')
            ->setStart('-1 months'))
            ->setName($this->translator->translate('payments.admin.payment_gateways.graph.completed_payments'));

        $graphDataItem2 = new GraphDataItem();
        $graphDataItem2->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('created_at')
            ->setWhere("AND status NOT IN (" . $paymentStatusCompleted . ") AND payment_gateway_id=" . intval($this->params['id']))
            ->setValueField('COUNT(*)')
            ->setStart('-1 months'))
            ->setName($this->translator->translate('payments.admin.payment_gateways.graph.uncompleted_payments'));

        $control = $factory->create()
            ->setGraphTitle($this->translator->translate('payments.admin.payment_gateways.graph.title'))
            ->setGraphHelp($this->translator->translate('payments.admin.payment_gateways.graph.help'))
            ->addGraphDataItem($graphDataItem1)
            ->addGraphDataItem($graphDataItem2);

        return $control;
    }
}
