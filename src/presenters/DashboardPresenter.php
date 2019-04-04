<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;

class DashboardPresenter extends AdminPresenter
{
    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    /** @persistent */
    public $recurrentCharge;

    public function startup()
    {
        parent::startup();
        $this->dateFrom = $this->dateFrom ?? DateTime::from('-2 months')->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
    }

    public function renderDefault()
    {
    }

    public function renderArpu()
    {
    }

    public function createComponentDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form[DateFilterFormFactory::OPTIONAL]->addSelect('recurrent_charge', 'Automatické obnovenie *', [
            'all' => 'Všetky',
            'recurrent' => 'Iba automaticky obnovené',
            'manual' => 'Iba manuálne',
        ]);
        $form[DateFilterFormFactory::OPTIONAL]->setDefaults(['recurrent_charge' => $this->recurrentCharge]);

        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->recurrentCharge = $values['optional']['recurrent_charge'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentGooglePaymentsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('paid_at')
            ->setWhere("AND payments.status = 'paid' {$this->recurrentChargeWhere()}")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.gateways.title') . ' *')
            ->setGraphHelp($this->translator->translate('dashboard.payments.gateways.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentGooglePaymentsAmountGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' {$this->recurrentChargeWhere()}")
            ->setValueField('sum(amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.money.title'));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.money.title') . ' *')
            ->setGraphHelp($this->translator->translate('dashboard.money.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentGoogleArpuGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' {$this->recurrentChargeWhere()}")
            ->setValueField('SUM(amount) / COUNT(*)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.arpu.graph_label'));

        $titleTransKey = 'dashboard.payments.arpu.all.title';
        $helpTransKey = 'dashboard.payments.arpu.all.tooltip';
        if ($this->recurrentCharge !== null) {
            $titleTransKey = "dashboard.payments.arpu.{$this->recurrentCharge}.title";
            $helpTransKey = "dashboard.payments.arpu.{$this->recurrentCharge}.tooltip";
        }

        $control = $factory->create();
        $control = $control->addGraphDataItem($graphDataItem)
            ->setGraphTitle($this->translator->translate($titleTransKey) . ' *')
            ->setGraphHelp($this->translator->translate($helpTransKey) . ' *');

        return $control;
    }

    public function createComponentGoogleSubscriptionsAndDonationsArpuGraph(
        GoogleLineGraphGroupControlFactoryInterface $factory
    ) {
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("
                AND payments.status = 'paid' {$this->recurrentChargeWhere()}
                AND payments.id NOT IN (
                    SELECT payment_id FROM payment_meta
                    WHERE `key` = 'crowdfunding' 
                    AND `value` = 1
                )
                AND payment_items.type IN ('donation', 'subscription_type')
            ")
            ->setJoin('JOIN payment_items ON payments.id = payment_items.payment_id')
            ->setValueField('SUM(payment_items.amount) / COUNT(payments.id)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.arpu.graph_label'));

        $titleTransKey = 'dashboard.payments.arpu_subscriptions.all.title';
        $helpTransKey = 'dashboard.payments.arpu_subscriptions.all.tooltip';
        if ($this->recurrentCharge !== null) {
            $titleTransKey = "dashboard.payments.arpu_subscriptions.{$this->recurrentCharge}.title";
            $helpTransKey = "dashboard.payments.arpu_subscriptions.{$this->recurrentCharge}.tooltip";
        }

        $control = $factory->create();
        $control = $control->addGraphDataItem($graphDataItem)
            ->setGraphTitle($this->translator->translate($titleTransKey) . ' *')
            ->setGraphHelp($this->translator->translate($helpTransKey) . ' *');

        return $control;
    }

    public function createComponentGoogleUserActiveSubscribersRegistrationsSourceStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();

        $graphDataItem->setCriteria(
            (new Criteria)->setTableName('payments')
                ->setTimeField('created_at')
                ->setJoin('JOIN users ON payments.user_id = users.id')
                ->setWhere("AND payments.status = '" . PaymentsRepository::STATUS_PAID . "'")
                ->setGroupBy('users.source')
                ->setSeries('users.source')
                ->setValueField('count(*)')
                ->setStart(DateTime::from($this->dateFrom))
                ->setEnd(DateTime::from($this->dateTo))
        );

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.registration.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.registration.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentGoogleRecurrentPaymentsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' AND payments.payment_gateway_id = 3 AND payments.id IN (SELECT recurrent_payments.payment_id FROM recurrent_payments WHERE state='charged')")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setTimeField('modified_at')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.recurrent.new'));
        $items[] = $graphDataItem;

        // TODO: duplicate graph data item, originally PAYMENTS_RECURRENT_CHARGED
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' AND payments.payment_gateway_id = 3 AND payments.id IN (SELECT recurrent_payments.payment_id FROM recurrent_payments WHERE state='charged')")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setTimeField('modified_at')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.recurrent.renewed'));
        $items[] = $graphDataItem;

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.recurrent.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.recurrent.tooltip'))
            ->setYLabel('');

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function createComponentGooglePaymentsRefundsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'refund' {$this->recurrentChargeWhere()}")
            ->setValueField('SUM(amount)')
            ->setTimeField('modified_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.refunds.title'));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.refunds.title') . ' *')
            ->setGraphHelp($this->translator->translate('dashboard.payments.refunds.tooltip'))
            ->addGraphDataItem($graphDataItem);

        return $control;
    }

    public function createComponentPaymentDonationsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' AND payments.additional_type = 'recurrent' {$this->recurrentChargeWhere()}")
            ->setValueField('SUM(additional_amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.donations.recurrent'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("AND payments.status = 'paid' AND payments.additional_type = 'single' {$this->recurrentChargeWhere()}")
            ->setValueField('SUM(additional_amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.donations.single'));
        $items[] = $graphDataItem;

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.donations.title') . ' *')
            ->setGraphHelp($this->translator->translate('dashboard.payments.donations.tooltip'))
            ->setYLabel('');

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function createComponentGoogleNewPayingUsersGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('users')
            ->setTimeField('created_at')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.users.user_registrations'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('users')
            ->setTimeField('created_at')
            ->setWhere('AND payments.id IS NOT NULL AND date(payments.paid_at) = calendar.date AND payments.amount > 0')
            ->setJoin('LEFT JOIN payments ON users.id = payments.user_id')
            ->setValueField('count(distinct users.id)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.users.subscribers'));
        $items[] = $graphDataItem;

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.users.new_or_subscribers.title'))
            ->setGraphHelp($this->translator->translate('dashboard.users.new_or_subscribers.tooltip'));

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    private function recurrentChargeWhere()
    {
        $where = "";
        if ($this->recurrentCharge === 'recurrent') {
            $where = 'AND payments.recurrent_charge = 1';
        } elseif ($this->recurrentCharge === 'manual') {
            $where = 'AND payments.recurrent_charge = 0';
        }
        return $where;
    }
}
