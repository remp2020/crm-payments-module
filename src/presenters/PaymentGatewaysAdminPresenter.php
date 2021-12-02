<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\InlineBarGraph;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphData;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\PaymentsModule\Components\LastPaymentsControlFactoryInterface;
use Crm\PaymentsModule\Forms\PaymentGatewayFormFactory;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette;
use Nette\Application\UI\Multiplier;

class PaymentGatewaysAdminPresenter extends AdminPresenter
{
    /** @inject */
    public GraphData $graphData;

    /** @var PaymentGatewaysRepository @inject */
    public $paymentGatewaysRepository;

    /** @var PaymentGatewayFormFactory @inject */
    public $factory;

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
        return $this->paymentGatewaysRepository->all($this->text)->order('sorting ASC');
    }

    /**
     * @admin-access-level read
     */
    public function renderShow($id)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($id);
        if (!$paymentGateway) {
            throw new Nette\Application\BadRequestException();
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

            $this->graphData->clear();
            $this->graphData->addGraphDataItem($graphDataItem);
            $this->graphData->setScaleRange('day');

            $data = $this->graphData->getData();
            if (!empty($data)) {
                $data = array_pop($data);
            }

            $control->setGraphTitle($this->translator->translate('payments.admin.payment_gateways.small_graph.title'))
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
