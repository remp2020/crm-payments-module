<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\InlineBarGraph;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Components\LastPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Forms\PaymentGatewayFormFactory;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette;
use Nette\Application\UI\Multiplier;

class PaymentGatewaysAdminPresenter extends AdminPresenter
{
    /** @var PaymentGatewaysRepository @inject */
    public $paymentGatewaysRepository;

    /** @var PaymentGatewayFormFactory @inject */
    public $factory;

    public function renderDefault()
    {
        $paymentGateways = $this->filteredGateways();
        $this->template->paymentGateways = $paymentGateways;
        $this->template->totalPaymentGateways = $this->paymentGatewaysRepository->totalCount();
    }

    private function filteredGateways()
    {
        return $this->paymentGatewaysRepository->all($this->text)->order('sorting ASC');
    }

    public function renderShow($id)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($id);
        if (!$paymentGateway) {
            throw new Nette\Application\BadRequestException();
        }
        $this->template->type = $paymentGateway;
    }

    public function renderEdit($id)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($id);
        if (!$paymentGateway) {
            throw new Nette\Application\BadRequestException();
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

    public function createComponentSmallGraph()
    {
        return new Multiplier(function ($id) {
            $control = new InlineBarGraph;

            $graphDataItem = new GraphDataItem();
            $graphDataItem
                ->setCriteria(
                    (new Criteria())
                        ->setTableName('payments')
                        ->setWhere('AND payment_gateway_id = ' . $id)
                        ->setGroupBy('payment_gateway_id')
                        ->setStart('-3 months')
                );

            $graphData = $this->context->getService('graph_data');
            $graphData->clear();
            $graphData->addGraphDataItem($graphDataItem);
            $graphData->setScaleRange('day');

            $data = $graphData->getData();
            if (!empty($data)) {
                $data = array_pop($data);
            }

            $control->setGraphTitle('Payments by Gateway')
                ->addSerie($data);
            return $control;
        });
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
            ->setName('Completed payments');

        $graphDataItem2 = new GraphDataItem();
        $graphDataItem2->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('created_at')
            ->setWhere("AND status NOT IN (" . $paymentStatusCompleted . ") AND payment_gateway_id=" . intval($this->params['id']))
            ->setValueField('COUNT(*)')
            ->setStart('-1 months'))
            ->setName('Uncompleted payments');

        $control = $factory->create()
            ->setGraphTitle('Gateway payments')
            ->setGraphHelp('Payments with actual gateways')
            ->addGraphDataItem($graphDataItem1)
            ->addGraphDataItem($graphDataItem2);

        return $control;
    }
}
