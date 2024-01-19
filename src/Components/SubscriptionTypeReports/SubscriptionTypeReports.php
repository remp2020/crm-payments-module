<?php

namespace Crm\PaymentsModule\Components\SubscriptionTypeReports;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Models\Report\NoRecurrentChargeReport;
use Crm\PaymentsModule\Models\Report\PaidNextSubscriptionReport;
use Crm\PaymentsModule\Models\Report\StoppedOnFirstSubscriptionReport;
use Crm\PaymentsModule\Models\Report\TotalPaidSubscriptionsReport;
use Crm\PaymentsModule\Models\Report\TotalRecurrentSubscriptionsReport;
use Crm\SubscriptionsModule\Models\Report\ReportGroup;
use Crm\SubscriptionsModule\Models\Report\ReportTable;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;

/**
 * This widget uses `ReportTable` to fetch different stats for specific subscription type
 * and renders table with resulting values.
 *
 * @package Crm\PaymentsModule\Components
 */
class SubscriptionTypeReports extends BaseLazyWidget
{
    private $templateName = 'subscription_type_reports.latte';

    private $subscriptionTypesRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        Translator $translator,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'subscriptiontypesreports';
    }

    public function render($subscriptionTypeId)
    {
        $reportTable = new ReportTable(
            ['subscription_type_id' => $subscriptionTypeId],
            $this->subscriptionTypesRepository->getDatabase(),
            new ReportGroup('users.source')
        );
        $reportTable
            ->addReport(new TotalPaidSubscriptionsReport('', $this->translator))
            ->addReport(new TotalRecurrentSubscriptionsReport('', $this->translator))
            ->addReport(new NoRecurrentChargeReport('', $this->translator), [TotalRecurrentSubscriptionsReport::class])
            ->addReport(new StoppedOnFirstSubscriptionReport('', $this->translator), [TotalRecurrentSubscriptionsReport::class])
            ->addReport(new PaidNextSubscriptionReport('', $this->translator, 1), [TotalRecurrentSubscriptionsReport::class])
            ->addReport(new PaidNextSubscriptionReport('', $this->translator, 2))
            ->addReport(new PaidNextSubscriptionReport('', $this->translator, 3))
        ;
        $this->template->reportTables = [
            $this->translator->translate('payments.admin.component.subscription_type_reports.title') => $reportTable->getData(),
        ];

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
