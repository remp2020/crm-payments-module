<?php

namespace Crm\PaymentsModule;

use Crm\ApiModule\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Router\ApiIdentifier;
use Crm\ApiModule\Router\ApiRoute;
use Crm\ApplicationModule\CallbackManagerInterface;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\WidgetManagerInterface;
use Crm\PaymentsModule\Commands\CalculateAveragesCommand;
use Crm\PaymentsModule\Commands\CidGetterCommand;
use Crm\PaymentsModule\Commands\CsobMailConfirmationCommand;
use Crm\PaymentsModule\Commands\LastPaymentsCheckCommand;
use Crm\PaymentsModule\Commands\RecurrentPaymentsCardCheck;
use Crm\PaymentsModule\Commands\RecurrentPaymentsChargeCommand;
use Crm\PaymentsModule\Commands\SingleChargeCommand;
use Crm\PaymentsModule\Commands\SkCsobMailConfirmationCommand;
use Crm\PaymentsModule\Commands\StopRecurrentPaymentsExpiresCommand;
use Crm\PaymentsModule\Commands\TatraBankaStatementMailConfirmationCommand;
use Crm\PaymentsModule\Commands\TatraBankaMailConfirmationCommand;
use Crm\PaymentsModule\Commands\UpdateRecurrentPaymentsExpiresCommand;
use Crm\PaymentsModule\DataProvider\CanDeleteAddressDataProvider;
use Crm\PaymentsModule\DataProvider\PaymentFromVariableSymbolDataProvider;
use Crm\PaymentsModule\DataProvider\SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodDataProvider;
use Crm\PaymentsModule\DataProvider\SubscriptionsWithoutExtensionEndingWithinPeriodDataProvider;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentStatusCriteria;
use Crm\PaymentsModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\PaymentsModule\Seeders\SegmentsSeeder;
use Kdyby\Translation\Translator;
use League\Event\Emitter;
use Nette\Application\Routers\Route;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;

class PaymentsModule extends CrmModule
{
    private $paymentsRepository;

    private $parsedMailLogsRepository;

    private $paymentsHistogramFactory;

    public function __construct(
        Container $container,
        Translator $translator,
        PaymentsRepository $paymentsRepository,
        ParsedMailLogsRepository $parsedMailLogsRepository,
        PaymentsHistogramFactory $paymentsHistogramFactory
    ) {
        parent::__construct($container, $translator);
        $this->paymentsRepository = $paymentsRepository;
        $this->parsedMailLogsRepository = $parsedMailLogsRepository;
        $this->paymentsHistogramFactory = $paymentsHistogramFactory;
    }

    public function registerAdminMenuItems(MenuContainerInterface $menuContainer)
    {
        $mainMenu = new MenuItem($this->translator->translate('payments.menu.admin_payments'), '#payments', 'fa fa-university', 200);

        $menuItem = new MenuItem($this->translator->translate('payments.menu.admin_payments'), ':Payments:PaymentsAdmin:', 'fa fa-university', 200);
        $mainMenu->addChild($menuItem);

        $menuItem = new MenuItem($this->translator->translate('payments.menu.gateways'), ':Payments:PaymentGatewaysAdmin:', 'fa fa-money-bill-alt', 300);
        $mainMenu->addChild($menuItem);

        $menuItem = new MenuItem(
            $this->translator->translate('payments.menu.parsed_mails'),
            ':Payments:ParsedMails:',
            'fa fa-clipboard-check',
            600,
            true
        );

        $mainMenu->addChild($menuItem);

        $menuItem = new MenuItem($this->translator->translate('payments.menu.recurrent_payments'), ':Payments:PaymentsRecurrentAdmin:', 'fa fa-sync-alt', 650);
        $mainMenu->addChild($menuItem);

        $menuItem = new MenuItem($this->translator->translate('payments.menu.duplicate_recurrent_payments'), ':Payments:PaymentsRecurrentAdmin:duplicates', 'fa fa-exclamation-triangle', 660);
        $mainMenu->addChild($menuItem);

        $menuContainer->attachMenuItem($mainMenu);

        // dashboard menu item

        $menuItem = new MenuItem(
            $this->translator->translate('payments.menu.stats'),
            ':Payments:Dashboard:default',
            'fa fa-money-bill-alt',
            300
        );
        $menuContainer->attachMenuItemToForeignModule('#dashboard', $mainMenu, $menuItem);
    }

    public function registerFrontendMenuItems(MenuContainerInterface $menuContainer)
    {
        $menuItem = new MenuItem($this->translator->translate('payments.menu.payments'), ':Payments:Payments:My', '', 100);
        $menuContainer->attachMenuItem($menuItem);
    }

    public function registerWidgets(WidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            $this->getInstance(\Crm\PaymentsModule\Components\UserPaymentsListing::class),
            200
        );
        $widgetManager->registerWidget(
            'admin.user.detail.box',
            $this->getInstance(\Crm\PaymentsModule\Components\TotalUserPayments::class),
            200
        );
        $widgetManager->registerWidget(
            'admin.payments.top',
            $this->getInstance(\Crm\PaymentsModule\Components\ParsedMailsFailedNotification::class),
            1000
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.totals',
            $this->getInstance(\Crm\PaymentsModule\Components\TotalAmountStatWidget::class),
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.subscribers',
            $this->getInstance(\Crm\PaymentsModule\Components\ActualPaidSubscribersStatWidget::class),
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.subscribers',
            $this->getInstance(\Crm\PaymentsModule\Components\ActualFreeSubscribersStatWidget::class),
            600
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.today',
            $this->getInstance(\Crm\PaymentsModule\Components\TodayAmountStatWidget::class),
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.month',
            $this->getInstance(\Crm\PaymentsModule\Components\MonthAmountStatWidget::class),
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.mtd',
            $this->getInstance(\Crm\PaymentsModule\Components\MonthToDateAmountStatWidget::class),
            700
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodWidget::class),
            700
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscriptionsWithoutExtensionEndingWithinPeriodWidget::class),
            800
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            $this->getInstance(\Crm\PaymentsModule\Components\PaidSubscriptionsWithoutExtensionEndingWithinPeriodWidget::class),
            900
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-1 day'),
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-7 days'),
            600
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-30 days'),
            600
        );
        $widgetManager->registerWidget(
            'admin.subscription_types.show.bottom_stats',
            $this->getInstance(\Crm\PaymentsModule\Components\SubscriptionTypeReports::class),
            500
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            $this->getInstance(\Crm\PaymentsModule\Components\PaymentItemsListWidget::class)
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            $this->getInstance(\Crm\PaymentsModule\Components\DonationPaymentItemsListWidget::class)
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_source_listing',
            $this->getInstance(\Crm\PaymentsModule\Components\DeviceUserListingWidget::class)
        );
        $widgetManager->registerWidget(
            'payments.frontend.payments_my.top',
            $this->getInstance(\Crm\PaymentsModule\Components\MyNextRecurrentPayment::class)
        );
        $widgetManager->registerWidget(
            'segment.detail.statspanel.row',
            $this->getInstance(\Crm\PaymentsModule\Components\AvgMonthPaymentWidget::class)
        );
        $widgetManager->registerWidget(
            'segment.detail.statspanel.row',
            $this->getInstance(\Crm\PaymentsModule\Components\AvgSubscriptionPaymentWidget::class)
        );
    }

    public function registerCommands(CommandsContainerInterface $commandsContainer)
    {
        $commandsContainer->registerCommand($this->getInstance(TatraBankaMailConfirmationCommand::class));
        $commandsContainer->registerCommand($this->getInstance(CsobMailConfirmationCommand::class));
        $commandsContainer->registerCommand($this->getInstance(RecurrentPaymentsCardCheck::class));
        $commandsContainer->registerCommand($this->getInstance(RecurrentPaymentsChargeCommand::class));
        $commandsContainer->registerCommand($this->getInstance(UpdateRecurrentPaymentsExpiresCommand::class));
        $commandsContainer->registerCommand($this->getInstance(CidGetterCommand::class));
        $commandsContainer->registerCommand($this->getInstance(StopRecurrentPaymentsExpiresCommand::class));
        $commandsContainer->registerCommand($this->getInstance(LastPaymentsCheckCommand::class));
        $commandsContainer->registerCommand($this->getInstance(CalculateAveragesCommand::class));
        $commandsContainer->registerCommand($this->getInstance(SingleChargeCommand::class));
        $commandsContainer->registerCommand($this->getInstance(SkCsobMailConfirmationCommand::class));
        $commandsContainer->registerCommand($this->getInstance(TatraBankaStatementMailConfirmationCommand::class));
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'payments', 'variable-symbol'),
                \Crm\PaymentsModule\Api\VariableSymbolApiHandler::class,
                \Crm\ApiModule\Authorization\AdminLoggedAuthorization::class
            )
        );
    }

    public function registerCleanupFunction(CallbackManagerInterface $cleanUpManager)
    {
        $cleanUpManager->add(function (Container $container) {
            $paymentsLogsRepository = $container->getByType('Crm\PaymentsModule\Repository\PaymentLogsRepository');
            $paymentsLogsRepository->removeOldData();
        });
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\PaymentsModule\User\PaymentsUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(\Crm\PaymentsModule\User\RecurrentPaymentsUserDataProvider::class));
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        $criteriaStorage->register('users', 'payment', $this->getInstance(\Crm\PaymentsModule\Segment\PaymentCriteria::class));
        $criteriaStorage->register('users', 'payment_counts', $this->getInstance(\Crm\PaymentsModule\Segment\PaymentCountsCriteria::class));
        $criteriaStorage->register('users', 'recurrent_payment', $this->getInstance(\Crm\PaymentsModule\Segment\RecurrentPaymentCriteria::class));

        $criteriaStorage->register('payments', 'amount', $this->getInstance(\Crm\PaymentsModule\Segment\AmountCriteria::class));
        $criteriaStorage->register('payments', 'status', $this->getInstance(\Crm\PaymentsModule\Segment\StatusCriteria::class));
        $criteriaStorage->register('payments', 'reference', $this->getInstance(\Crm\PaymentsModule\Segment\ReferenceCriteria::class));

        $criteriaStorage->setDefaultFields('payments', ['id']);
        $criteriaStorage->setFields('payments', [
            'variable_symbol',
            'amount',
            'additional_amount',
            'additional_type',
            'status',
            'created_at',
            'paid_at',
            'recurrent_charge',
        ]);
    }

    public function registerScenariosCriteria(ScenariosCriteriaStorage $scenariosCriteriaStorage)
    {
        $scenariosCriteriaStorage->register('payment', 'status', $this->getInstance(PaymentStatusCriteria::class));
    }

    public function registerSeeders(SeederManager $seederManager)
    {
        $seederManager->addSeeder($this->getInstance(ConfigsSeeder::class));
        $seederManager->addSeeder($this->getInstance(PaymentGatewaysSeeder::class));
        $seederManager->addSeeder($this->getInstance(SegmentsSeeder::class));
    }

    public function registerDataProviders(DataProviderManager $dataProviderManager)
    {
        $dataProviderManager->registerDataProvider(
            'subscriptions.dataprovider.ending_subscriptions',
            $this->getInstance(SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'subscriptions.dataprovider.ending_subscriptions',
            $this->getInstance(SubscriptionsWithoutExtensionEndingWithinPeriodDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'subscriptions.dataprovider.payment_from_variable_symbol',
            $this->getInstance(PaymentFromVariableSymbolDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.address.can_delete',
            $this->getInstance(CanDeleteAddressDataProvider::class)
        );
    }

    public function registerEventHandlers(Emitter $emitter)
    {
        $emitter->addListener(
            \Crm\PaymentsModule\Events\PaymentChangeStatusEvent::class,
            $this->getInstance(\Crm\PaymentsModule\Events\PaymentStatusChangeHandler::class),
            500
        );
        $emitter->addListener(
            \Crm\SubscriptionsModule\Events\SubscriptionPreUpdateEvent::class,
            $this->getInstance(\Crm\PaymentsModule\Events\SubscriptionPreUpdateHandler::class)
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('new_payment', Events\NewPaymentEvent::class);
        $eventsStorage->register('payment_change_status', Events\PaymentChangeStatusEvent::class);
        $eventsStorage->register('recurrent_payment_fail', Events\RecurrentPaymentFailEvent::class);
        $eventsStorage->register('recurrent_payment_fail_try', Events\RecurrentPaymentFailTryEvent::class);
        $eventsStorage->register('recurrent_payment_renewed', Events\RecurrentPaymentRenewedEvent::class, true);
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (in_array('precalc', $tags, true)) {
            $output->writeln('  * Refreshing <info>payment stats</info> cache');

            $this->paymentsRepository->totalCount(true, true);
            $this->paymentsRepository->totalAmountSum(true, true);
            $this->paymentsRepository->freeSubscribersCount(true, true);
            $this->paymentsRepository->paidSubscribersCount(true, true);

            $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextTwoWeeksCount(true);
            $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextMonthCount(true);
            $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextTwoWeeksCount(true, true);
            $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextMonthCount(true, true);
            $this->paymentsRepository->subscriptionsWithActiveUnchargedRecurrentEndingNextTwoWeeksCount(true);
            $this->paymentsRepository->subscriptionsWithActiveUnchargedRecurrentEndingNextMonthCount(true);

            $cachedPaymentStatusHistograms = [
                PaymentsRepository::STATUS_FORM,
                PaymentsRepository::STATUS_PAID,
                PaymentsRepository::STATUS_FAIL,
                PaymentsRepository::STATUS_TIMEOUT,
                PaymentsRepository::STATUS_REFUND,
            ];

            foreach ($cachedPaymentStatusHistograms as $status) {
                $this->paymentsHistogramFactory->paymentsLastMonthDailyHistogram($status, true);
            }

            $this->parsedMailLogsRepository->formPaymentsWithWrongAmount(true);
        }
    }

    public function registerRoutes(RouteList $router)
    {
        $router[] = new Route('payments/return/gateway/<gatewayCode>', 'Payments:Return:gateway');
    }
}
