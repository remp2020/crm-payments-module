<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Components\DateFilterFormFactory;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\Graphs\GoogleBarGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\Components\Graphs\GoogleLineGraphGroupControlFactoryInterface;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Graphs\Criteria;
use Crm\ApplicationModule\Graphs\GraphDataItem;
use Crm\PaymentsModule\DataProvider\PaymentItemTypesFilterDataProviderInterface;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class DashboardPresenter extends AdminPresenter
{
    const PAID_PAYMENT_STATUSES = [
        PaymentsRepository::STATUS_PAID,
        PaymentsRepository::STATUS_PREPAID,
    ];

    /** @var PaymentItemsRepository @inject */
    public $paymentItemsRepository;

    /** @persistent */
    public $dateFrom;

    /** @persistent */
    public $dateTo;

    /** @persistent */
    public $recurrentCharge;

    /** @persistent */
    public $paymentItemTypes;

    private $dataProviderManager;

    private $availablePaymentItemTypesFilter = [];

    private $defaultPaymentItemTypesFilter = [];

    public function __construct(
        DataProviderManager $dataProviderManager
    ) {
        parent::__construct();
        $this->dataProviderManager = $dataProviderManager;
    }

    public function startup()
    {
        parent::startup();
        $this->getPaymentItemTypes();
        $this->dateFrom = $this->dateFrom ?? DateTime::from('-2 months')->format('Y-m-d');
        $this->dateTo = $this->dateTo ?? DateTime::from('today')->format('Y-m-d');
        $this->paymentItemTypes = $this->paymentItemTypes ?? Json::encode($this->defaultPaymentItemTypesFilter);
    }

    private function getPaymentItemTypes(): void
    {
        $filters = ['paymentItemTypes' => [], 'paymentItemTypesDefaultFilter' => []];

        /** @var PaymentItemTypesFilterDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.dashboard', PaymentItemTypesFilterDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $filters = $provider->provide($filters);
        }

        $this->availablePaymentItemTypesFilter = $filters['paymentItemTypes'];
        $this->defaultPaymentItemTypesFilter = $filters['paymentItemTypesDefaultFilter'];
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
    }

    /**
     * @admin-access-level read
     */
    public function renderArpu()
    {
        $this->template->graphParams = [
            'recurrentCharge' => $this->recurrentCharge,
            'dateFrom' => $this->dateFrom,
            'dateTo' => $this->dateTo,
            'paymentItemTypes' => $this->paymentItemTypes
        ];
    }

    /**
     * @admin-access-level read
     */
    public function renderDetailed()
    {
    }

    public function createComponentDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form->setTranslator($this->translator);
        $form[DateFilterFormFactory::OPTIONAL]->addSelect('recurrent_charge', 'payments.admin.dashboard.recurrent_charge.label', [
            'all' => $this->translator->translate('payments.admin.dashboard.recurrent_charge.all'),
            'recurrent' => $this->translator->translate('payments.admin.dashboard.recurrent_charge.recurrent'),
            'manual' => $this->translator->translate('payments.admin.dashboard.recurrent_charge.manual'),
        ]);

        if (!empty($this->availablePaymentItemTypesFilter)) {
            $form[DateFilterFormFactory::OPTIONAL]
                ->addMultiSelect(
                    'payment_item_types',
                    'payments.admin.dashboard.payment_item_types.label',
                    $this->availablePaymentItemTypesFilter
                )
                ->getControlPrototype()->addAttributes(['class' => 'select2'])->setAttribute('style', 'min-width:200px');
        }

        $form[DateFilterFormFactory::OPTIONAL]->setDefaults([
            'recurrent_charge' => $this->recurrentCharge,
            'payment_item_types' => Json::decode($this->paymentItemTypes)
        ]);

        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->recurrentCharge = $values[DateFilterFormFactory::OPTIONAL]['recurrent_charge'];
            $this->paymentItemTypes = Json::encode($values[DateFilterFormFactory::OPTIONAL]['payment_item_types'] ?? null);
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentSimpleDateFilterForm(DateFilterFormFactory $dateFilterFormFactory)
    {
        $form = $dateFilterFormFactory->create($this->dateFrom, $this->dateTo);
        $form->setTranslator($this->translator);

        $form->onSuccess[] = function ($form, $values) {
            $this->dateFrom = $values['date_from'];
            $this->dateTo = $values['date_to'];
            $this->redirect($this->action);
        };
        return $form;
    }

    public function createComponentGooglePaymentsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setTimeField('paid_at')
            ->setWhere("{$this->paidPaymentStatusWhere()} {$this->recurrentChargeWhere()} {$this->paymentItemTypesWhere()}")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id JOIN payment_items ON payment_items.payment_id = payments.id')
            ->setSeries('payment_gateways.name')
            ->setValueField('COUNT(DISTINCT payments.id)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.gateways.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.gateways.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGooglePaymentsAmountGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setJoin('JOIN payment_items ON payment_items.payment_id = payments.id')
            ->setWhere("{$this->paidPaymentStatusWhere()} {$this->recurrentChargeWhere()} {$this->paymentItemTypesWhere()}")
            ->setValueField('sum(payment_items.count * payment_items.amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.money.title'));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.money.title'))
            ->setGraphHelp($this->translator->translate('dashboard.money.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleArpuGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setJoin('JOIN payment_items ON payment_items.payment_id = payments.id')
            ->setWhere("{$this->paidPaymentStatusWhere()} {$this->recurrentChargeWhere()} {$this->paymentItemTypesWhere()}")
            ->setValueField('SUM(payment_items.count * payment_items.amount) / COUNT(DISTINCT payments.id)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('payments.admin.arpu.graph_label'));

        $titleTransKey = 'payments.admin.arpu.all.title';
        $helpTransKey = 'payments.admin.arpu.all.tooltip';
        if ($this->recurrentCharge !== null) {
            $titleTransKey = "payments.admin.arpu.{$this->recurrentCharge}.title";
            $helpTransKey = "payments.admin.arpu.{$this->recurrentCharge}.tooltip";
        }

        $control = $factory->create();
        $control = $control->addGraphDataItem($graphDataItem)
            ->setGraphTitle($this->translator->translate($titleTransKey))
            ->setGraphHelp($this->translator->translate($helpTransKey))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleUserActiveSubscribersRegistrationsSourceStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();

        $graphDataItem->setCriteria(
            (new Criteria)->setTableName('payments')
                ->setTimeField('created_at')
                ->setJoin('JOIN users ON payments.user_id = users.id JOIN payment_items ON payment_items.payment_id = payments.id')
                ->setWhere("{$this->paidPaymentStatusWhere()} {$this->recurrentChargeWhere()} {$this->paymentItemTypesWhere()}")
                ->setGroupBy('users.source')
                ->setSeries('users.source')
                ->setValueField('count(DISTINCT payments.id)')
                ->setStart(DateTime::from($this->dateFrom))
                ->setEnd(DateTime::from($this->dateTo))
        );

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.registration.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.registration.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentGoogleRecurrentPaymentsStatsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("{$this->paidPaymentStatusWhere()} AND payments.recurrent_charge = 0 AND payments.payment_gateway_id IN (SELECT id FROM payment_gateways WHERE is_recurrent = 1)")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setTimeField('modified_at')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.recurrent.new') . ' ');
        $items[] = $graphDataItem;

        // TODO: duplicate graph data item, originally PAYMENTS_RECURRENT_CHARGED
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("{$this->paidPaymentStatusWhere()} AND payments.recurrent_charge = 1 AND payments.payment_gateway_id IN (SELECT id FROM payment_gateways WHERE is_recurrent = 1)")
            ->setGroupBy('payment_gateways.name')
            ->setJoin('LEFT JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id')
            ->setSeries('payment_gateways.name')
            ->setTimeField('modified_at')
            ->setValueField('count(*)')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.recurrent.renewed') . ' ');
        $items[] = $graphDataItem;

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.recurrent.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.recurrent.tooltip'))
            ->setYLabel('')
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function createComponentGooglePaymentsRefundsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setJoin('JOIN payment_items ON payment_items.payment_id = payments.id')
            ->setWhere("AND payments.status = 'refund' {$this->recurrentChargeWhere()} {$this->paymentItemTypesWhere()}")
            ->setValueField('SUM(payment_items.count * payment_items.amount)')
            ->setTimeField('modified_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.refunds.title'));

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.refunds.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.refunds.tooltip'))
            ->addGraphDataItem($graphDataItem)
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        return $control;
    }

    public function createComponentPaymentDonationsGraph(GoogleBarGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
        $items = [];

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("{$this->paidPaymentStatusWhere()} AND payments.additional_type = 'recurrent'")
            ->setValueField('SUM(additional_amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.donations.recurrent'));
        $items[] = $graphDataItem;

        $graphDataItem = new GraphDataItem();
        $graphDataItem->setCriteria((new Criteria())
            ->setTableName('payments')
            ->setWhere("{$this->paidPaymentStatusWhere()} AND payments.additional_type = 'single'")
            ->setValueField('SUM(additional_amount)')
            ->setTimeField('paid_at')
            ->setStart($this->dateFrom)
            ->setEnd($this->dateTo));
        $graphDataItem->setName($this->translator->translate('dashboard.payments.donations.single'));
        $items[] = $graphDataItem;

        $control = $factory->create();
        $control->setGraphTitle($this->translator->translate('dashboard.payments.donations.title'))
            ->setGraphHelp($this->translator->translate('dashboard.payments.donations.tooltip'))
            ->setYLabel('')
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function createComponentGoogleNewPayingUsersGraph(GoogleLineGraphGroupControlFactoryInterface $factory)
    {
        $this->getSession()->close();
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
            ->setGraphHelp($this->translator->translate('dashboard.users.new_or_subscribers.tooltip'))
            ->setFrom($this->dateFrom)
            ->setTo($this->dateTo);

        foreach ($items as $graphDataItem) {
            $control->addGraphDataItem($graphDataItem);
        }

        return $control;
    }

    public function paidPaymentStatusWhere(): string
    {
        return "AND payments.status IN ('" . implode("','", self::PAID_PAYMENT_STATUSES) . "')";
    }

    public function recurrentChargeWhere()
    {
        $where = "";
        if ($this->recurrentCharge === 'recurrent') {
            $where = 'AND payments.recurrent_charge = 1';
        } elseif ($this->recurrentCharge === 'manual') {
            $where = 'AND payments.recurrent_charge = 0';
        }
        return $where;
    }

    public function paymentItemTypesWhere(): string
    {
        $selectedTypes = Json::decode($this->paymentItemTypes);
        if (empty($selectedTypes)) {
            return "";
        }

        $filter = [];
         /** @var PaymentItemTypesFilterDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.dashboard', PaymentItemTypesFilterDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $filter[] = $provider->filter($selectedTypes);
        }

        $filter = array_filter($filter);
        if (empty($filter)) {
            return "";
        }

        return "AND (" . implode(" OR ", $filter) . ")";
    }
}
