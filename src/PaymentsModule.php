<?php

namespace Crm\PaymentsModule;

use Contributte\Translation\Translator;
use Crm\ApiModule\Models\Api\ApiRoutersContainerInterface;
use Crm\ApiModule\Models\Authorization\AdminLoggedAuthorization;
use Crm\ApiModule\Models\Authorization\NoAuthorization;
use Crm\ApiModule\Models\Router\ApiIdentifier;
use Crm\ApiModule\Models\Router\ApiRoute;
use Crm\ApplicationModule\CallbackManagerInterface;
use Crm\ApplicationModule\Commands\CommandsContainerInterface;
use Crm\ApplicationModule\Criteria\CriteriaStorage;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaStorage;
use Crm\ApplicationModule\CrmModule;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Event\EventsStorage;
use Crm\ApplicationModule\Event\LazyEventEmitter;
use Crm\ApplicationModule\Menu\MenuContainerInterface;
use Crm\ApplicationModule\Menu\MenuItem;
use Crm\ApplicationModule\SeederManager;
use Crm\ApplicationModule\User\UserDataRegistrator;
use Crm\ApplicationModule\Widget\LazyWidgetManagerInterface;
use Crm\PaymentsModule\Api\ListRecurrentPaymentsApiHandler;
use Crm\PaymentsModule\Api\PaypalIpnHandler;
use Crm\PaymentsModule\Api\ReactivateRecurrentPaymentApiHandler;
use Crm\PaymentsModule\Api\StopRecurrentPaymentApiHandler;
use Crm\PaymentsModule\Api\VariableSymbolApiHandler;
use Crm\PaymentsModule\Commands\CalculateAveragesCommand;
use Crm\PaymentsModule\Commands\CancelAuthorizationCommand;
use Crm\PaymentsModule\Commands\CidGetterCommand;
use Crm\PaymentsModule\Commands\ConfirmCsobPaymentsCommand;
use Crm\PaymentsModule\Commands\CsobMailConfirmationCommand;
use Crm\PaymentsModule\Commands\FillReferenceToSubscriptionTypeItemInPaymentItemsCommand;
use Crm\PaymentsModule\Commands\LastPaymentsCheckCommand;
use Crm\PaymentsModule\Commands\RecurrentPaymentsCardCheck;
use Crm\PaymentsModule\Commands\RecurrentPaymentsChargeCommand;
use Crm\PaymentsModule\Commands\SingleChargeCommand;
use Crm\PaymentsModule\Commands\SkCsobMailConfirmationCommand;
use Crm\PaymentsModule\Commands\StopRecurrentPaymentsExpiresCommand;
use Crm\PaymentsModule\Commands\TatraBankaMailConfirmationCommand;
use Crm\PaymentsModule\Commands\TatraBankaStatementMailConfirmationCommand;
use Crm\PaymentsModule\Commands\UpdateRecurrentPaymentsExpiresCommand;
use Crm\PaymentsModule\Components\ActualFreeSubscribersStatWidget;
use Crm\PaymentsModule\Components\ActualPaidSubscribersStatWidget;
use Crm\PaymentsModule\Components\AddressWidget;
use Crm\PaymentsModule\Components\AuthorizationPaymentItemListWidget;
use Crm\PaymentsModule\Components\AvgMonthPaymentWidget;
use Crm\PaymentsModule\Components\AvgSubscriptionPaymentWidget;
use Crm\PaymentsModule\Components\ChangePaymentSubscriptionTypeWidget;
use Crm\PaymentsModule\Components\DeviceUserListingWidget;
use Crm\PaymentsModule\Components\DonationPaymentItemsListWidget;
use Crm\PaymentsModule\Components\MonthAmountStatWidget;
use Crm\PaymentsModule\Components\MonthToDateAmountStatWidget;
use Crm\PaymentsModule\Components\MyNextRecurrentPayment;
use Crm\PaymentsModule\Components\PaidSubscriptionsWithoutExtensionEndingWithinPeriodWidget;
use Crm\PaymentsModule\Components\ParsedMailsFailedNotification;
use Crm\PaymentsModule\Components\PaymentDonationLabelWidget;
use Crm\PaymentsModule\Components\PaymentItemsListWidget;
use Crm\PaymentsModule\Components\PaymentToSubscriptionMenu;
use Crm\PaymentsModule\Components\ReactivateFailedRecurrentPaymentWidget\ReactivateFailedRecurrentPaymentWidget;
use Crm\PaymentsModule\Components\RefundPaymentsListWidget;
use Crm\PaymentsModule\Components\SubscribersWithPaymentWidgetFactory;
use Crm\PaymentsModule\Components\SubscriptionDetailWidget\SubscriptionDetailWidget;
use Crm\PaymentsModule\Components\SubscriptionTypeReports;
use Crm\PaymentsModule\Components\SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodWidget;
use Crm\PaymentsModule\Components\SubscriptionsWithoutExtensionEndingWithinPeriodWidget;
use Crm\PaymentsModule\Components\TodayAmountStatWidget;
use Crm\PaymentsModule\Components\TotalAmountStatWidget;
use Crm\PaymentsModule\Components\TotalUserPayments;
use Crm\PaymentsModule\Components\UserPaymentsListing;
use Crm\PaymentsModule\DataProvider\CanDeleteAddressDataProvider;
use Crm\PaymentsModule\DataProvider\PaymentFromVariableSymbolDataProvider;
use Crm\PaymentsModule\DataProvider\PaymentItemTypesFilterDataProvider;
use Crm\PaymentsModule\DataProvider\PaymentsClaimUserDataProvider;
use Crm\PaymentsModule\DataProvider\RecurrentPaymentsClaimUserDataProvider;
use Crm\PaymentsModule\DataProvider\SubscriptionFormDataProvider;
use Crm\PaymentsModule\DataProvider\SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodDataProvider;
use Crm\PaymentsModule\DataProvider\SubscriptionsWithoutExtensionEndingWithinPeriodDataProvider;
use Crm\PaymentsModule\DataProvider\UniversalSearchDataProvider;
use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Events\NewPaymentEvent;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Events\PaymentStatusChangeHandler;
use Crm\PaymentsModule\Events\RecurrentPaymentCardExpiredEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentCardExpiredEventHandler;
use Crm\PaymentsModule\Events\RecurrentPaymentFailEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentFailTryEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentStateChangedEvent;
use Crm\PaymentsModule\Events\SubscriptionMovedHandler;
use Crm\PaymentsModule\Events\SubscriptionPreUpdateHandler;
use Crm\PaymentsModule\Hermes\ExportPaymentsHandler;
use Crm\PaymentsModule\Hermes\RetentionAnalysisJobHandler;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Models\AverageMonthPayment;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\DonationAmountCriteria;
use Crm\PaymentsModule\Scenarios\IsActiveRecurrentSubscriptionCriteria;
use Crm\PaymentsModule\Scenarios\PaymentGatewayCriteria;
use Crm\PaymentsModule\Scenarios\PaymentHasItemTypeCriteria;
use Crm\PaymentsModule\Scenarios\PaymentHasSubscriptionCriteria;
use Crm\PaymentsModule\Scenarios\PaymentIsRecurrentChargeCriteria;
use Crm\PaymentsModule\Scenarios\PaymentStatusCriteria;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentStateCriteria;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentStatusCriteria;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentSubscriptionTypeContentAccessCriteria;
use Crm\PaymentsModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\PaymentsModule\Seeders\SegmentsSeeder;
use Crm\PaymentsModule\Segment\AmountCriteria;
use Crm\PaymentsModule\Segment\PaymentCountsCriteria;
use Crm\PaymentsModule\Segment\PaymentCriteria;
use Crm\PaymentsModule\Segment\RecurrentPaymentCriteria;
use Crm\PaymentsModule\Segment\ReferenceCriteria;
use Crm\PaymentsModule\Segment\StatusCriteria;
use Crm\PaymentsModule\User\PaymentsUserDataProvider;
use Crm\PaymentsModule\User\RecurrentPaymentsUserDataProvider;
use Crm\SubscriptionsModule\DataProviders\SubscriptionFormDataProviderInterface;
use Crm\SubscriptionsModule\Events\SubscriptionMovedEvent;
use Crm\SubscriptionsModule\Events\SubscriptionPreUpdateEvent;
use Crm\UsersModule\Auth\UserTokenAuthorization;
use Nette\Application\Routers\RouteList;
use Nette\DI\Container;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\Hermes\Dispatcher;

class PaymentsModule extends CrmModule
{
    private $paymentsRepository;

    private $parsedMailLogsRepository;

    private $paymentsHistogramFactory;
    private $averageMonthPayment;

    public function __construct(
        Container $container,
        Translator $translator,
        PaymentsRepository $paymentsRepository,
        ParsedMailLogsRepository $parsedMailLogsRepository,
        PaymentsHistogramFactory $paymentsHistogramFactory,
        AverageMonthPayment $averageMonthPayment
    ) {
        parent::__construct($container, $translator);
        $this->paymentsRepository = $paymentsRepository;
        $this->parsedMailLogsRepository = $parsedMailLogsRepository;
        $this->paymentsHistogramFactory = $paymentsHistogramFactory;
        $this->averageMonthPayment = $averageMonthPayment;
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

        $menuItem = new MenuItem($this->translator->translate('payments.menu.retention_analysis'), ':Payments:RetentionAnalysisAdmin:', 'fa fa-chart-line', 670);
        $mainMenu->addChild($menuItem);

        $menuItem = new MenuItem($this->translator->translate('payments.menu.exports'), ':Payments:ExportsAdmin:', 'fa fa-download', 680);
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

    public function registerLazyWidgets(LazyWidgetManagerInterface $widgetManager)
    {
        $widgetManager->registerWidget(
            'admin.user.detail.bottom',
            UserPaymentsListing::class,
            200
        );
        $widgetManager->registerWidget(
            'admin.user.detail.box',
            TotalUserPayments::class,
            200
        );
        $widgetManager->registerWidget(
            'admin.payments.top',
            ParsedMailsFailedNotification::class,
            1000
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.totals',
            TotalAmountStatWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.subscribers',
            ActualPaidSubscribersStatWidget::class,
            500
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.actuals.subscribers',
            ActualFreeSubscribersStatWidget::class,
            600
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.today',
            TodayAmountStatWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.month',
            MonthAmountStatWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'dashboard.singlestat.mtd',
            MonthToDateAmountStatWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodWidget::class,
            700
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            SubscriptionsWithoutExtensionEndingWithinPeriodWidget::class,
            800
        );
        $widgetManager->registerWidget(
            'subscriptions.endinglist',
            PaidSubscriptionsWithoutExtensionEndingWithinPeriodWidget::class,
            900
        );
        $widgetManager->registerWidgetWithInstance(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-1 day'),
            500
        );
        $widgetManager->registerWidgetWithInstance(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-7 days'),
            600
        );
        $widgetManager->registerWidgetWithInstance(
            'dashboard.singlestat.actuals.system',
            $this->getInstance(SubscribersWithPaymentWidgetFactory::class)->create()->setDateModifier('-30 days'),
            600
        );
        $widgetManager->registerWidget(
            'admin.subscription_types.show.bottom_stats',
            SubscriptionTypeReports::class,
            500
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            PaymentItemsListWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            DonationPaymentItemsListWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_item_listing',
            AuthorizationPaymentItemListWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.payment_source_listing',
            DeviceUserListingWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.listing.sum',
            PaymentDonationLabelWidget::class
        );
        $widgetManager->registerWidget(
            'payments.frontend.payments_my.top',
            MyNextRecurrentPayment::class
        );
        $widgetManager->registerWidget(
            'payments.frontend.payments_my.bottom',
            RefundPaymentsListWidget::class
        );
        $widgetManager->registerWidget(
            'frontend.payment.success.bottom',
            AddressWidget::class
        );
        $widgetManager->registerWidget(
            'segment.detail.statspanel.row',
            AvgMonthPaymentWidget::class
        );
        $widgetManager->registerWidget(
            'segment.detail.statspanel.row',
            AvgSubscriptionPaymentWidget::class
        );
        $widgetManager->registerWidget(
            'payments.admin.edit_form.after',
            ChangePaymentSubscriptionTypeWidget::class
        );

        $widgetManager->registerWidget(
            'payments.admin.user_payments.listing.recurrent.actions',
            ReactivateFailedRecurrentPaymentWidget::class,
        );
        $widgetManager->registerWidget(
            'admin.subscriptions.show.right',
            SubscriptionDetailWidget::class
        );

        $widgetManager->registerWidget(
            'subscriptions.admin.user_subscriptions_listing.action.menu',
            PaymentToSubscriptionMenu::class,
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
        $commandsContainer->registerCommand($this->getInstance(CancelAuthorizationCommand::class));
        $commandsContainer->registerCommand($this->getInstance(FillReferenceToSubscriptionTypeItemInPaymentItemsCommand::class));
        $commandsContainer->registerCommand($this->getInstance(ConfirmCsobPaymentsCommand::class));
    }

    public function registerApiCalls(ApiRoutersContainerInterface $apiRoutersContainer)
    {
        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'payments', 'variable-symbol'),
                VariableSymbolApiHandler::class,
                AdminLoggedAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'users', 'recurrent-payments'),
                ListRecurrentPaymentsApiHandler::class,
                UserTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'recurrent-payment', 'reactivate'),
                ReactivateRecurrentPaymentApiHandler::class,
                UserTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'recurrent-payment', 'stop'),
                StopRecurrentPaymentApiHandler::class,
                UserTokenAuthorization::class
            )
        );

        $apiRoutersContainer->attachRouter(
            new ApiRoute(
                new ApiIdentifier('1', 'payments', 'paypal-ipn'),
                PaypalIpnHandler::class,
                NoAuthorization::class
            )
        );
    }

    public function registerCleanupFunction(CallbackManagerInterface $cleanUpManager)
    {
        $cleanUpManager->add(PaymentLogsRepository::class, function (Container $container) {
            /** @var PaymentLogsRepository $paymentsLogsRepository */
            $paymentsLogsRepository = $container->getByType(PaymentLogsRepository::class);
            $paymentsLogsRepository->removeOldData();
        });
    }

    public function registerUserData(UserDataRegistrator $dataRegistrator)
    {
        $dataRegistrator->addUserDataProvider($this->getInstance(PaymentsUserDataProvider::class));
        $dataRegistrator->addUserDataProvider($this->getInstance(RecurrentPaymentsUserDataProvider::class));
    }

    public function registerSegmentCriteria(CriteriaStorage $criteriaStorage)
    {
        $criteriaStorage->register('users', 'payment', $this->getInstance(PaymentCriteria::class));
        $criteriaStorage->register('users', 'payment_counts', $this->getInstance(PaymentCountsCriteria::class));
        $criteriaStorage->register('users', 'recurrent_payment', $this->getInstance(RecurrentPaymentCriteria::class));

        $criteriaStorage->register('payments', 'amount', $this->getInstance(AmountCriteria::class));
        $criteriaStorage->register('payments', 'status', $this->getInstance(StatusCriteria::class));
        $criteriaStorage->register('payments', 'reference', $this->getInstance(ReferenceCriteria::class));

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
        $scenariosCriteriaStorage->register('payment', PaymentIsRecurrentChargeCriteria::KEY, $this->getInstance(PaymentIsRecurrentChargeCriteria::class));
        $scenariosCriteriaStorage->register('payment', PaymentGatewayCriteria::KEY, $this->getInstance(PaymentGatewayCriteria::class));
        $scenariosCriteriaStorage->register('payment', PaymentHasSubscriptionCriteria::KEY, $this->getInstance(PaymentHasSubscriptionCriteria::class));
        $scenariosCriteriaStorage->register('payment', DonationAmountCriteria::KEY, $this->getInstance(DonationAmountCriteria::class));
        $scenariosCriteriaStorage->register('payment', PaymentHasItemTypeCriteria::KEY, $this->getInstance(PaymentHasItemTypeCriteria::class));
        $scenariosCriteriaStorage->register('subscription', IsActiveRecurrentSubscriptionCriteria::KEY, $this->getInstance(IsActiveRecurrentSubscriptionCriteria::class));
        $scenariosCriteriaStorage->register('recurrent_payment', RecurrentPaymentStateCriteria::KEY, $this->getInstance(RecurrentPaymentStateCriteria::class));
        $scenariosCriteriaStorage->register('recurrent_payment', RecurrentPaymentStatusCriteria::KEY, $this->getInstance(RecurrentPaymentStatusCriteria::class));
        $scenariosCriteriaStorage->register('recurrent_payment', RecurrentPaymentSubscriptionTypeContentAccessCriteria::KEY, $this->getInstance(RecurrentPaymentSubscriptionTypeContentAccessCriteria::class));
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
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.claim_unclaimed_user',
            $this->getInstance(PaymentsClaimUserDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'users.dataprovider.claim_unclaimed_user',
            $this->getInstance(RecurrentPaymentsClaimUserDataProvider::class)
        );
        $dataProviderManager->registerDataProvider(
            'payments.dataprovider.dashboard',
            $this->getInstance(PaymentItemTypesFilterDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            SubscriptionFormDataProviderInterface::PATH,
            $this->getInstance(SubscriptionFormDataProvider::class)
        );

        $dataProviderManager->registerDataProvider(
            'admin.dataprovider.universal_search',
            $this->getInstance(UniversalSearchDataProvider::class)
        );
    }

    public function registerLazyEventHandlers(LazyEventEmitter $emitter)
    {
        $emitter->addListener(
            PaymentChangeStatusEvent::class,
            PaymentStatusChangeHandler::class,
            500
        );
        $emitter->addListener(
            SubscriptionPreUpdateEvent::class,
            SubscriptionPreUpdateHandler::class
        );
        $emitter->addListener(
            RecurrentPaymentCardExpiredEvent::class,
            RecurrentPaymentCardExpiredEventHandler::class
        );
        $emitter->addListener(
            SubscriptionMovedEvent::class,
            SubscriptionMovedHandler::class
        );
    }

    public function registerHermesHandlers(Dispatcher $dispatcher)
    {
        $dispatcher->registerHandler(
            'retention-analysis-job',
            $this->getInstance(RetentionAnalysisJobHandler::class)
        );
        $dispatcher->registerHandler(
            'export-payments',
            $this->getInstance(ExportPaymentsHandler::class)
        );
    }

    public function registerEvents(EventsStorage $eventsStorage)
    {
        $eventsStorage->register('new_payment', NewPaymentEvent::class, true);
        $eventsStorage->register('payment_change_status', PaymentChangeStatusEvent::class, true);
        $eventsStorage->register('recurrent_payment_fail', RecurrentPaymentFailEvent::class);
        $eventsStorage->register('recurrent_payment_fail_try', RecurrentPaymentFailTryEvent::class);
        $eventsStorage->register('recurrent_payment_renewed', RecurrentPaymentRenewedEvent::class, true);
        $eventsStorage->register('card_expires_this_month', RecurrentPaymentCardExpiredEvent::class);
        $eventsStorage->register('recurrent_payment_state_changed', RecurrentPaymentStateChangedEvent::class, true);
        $eventsStorage->register('before_recurrent_payment_charge', BeforeRecurrentPaymentChargeEvent::class, true);
    }

    public function cache(OutputInterface $output, array $tags = [])
    {
        if (in_array('precalc', $tags, true)) {
            $output->writeln('  * Refreshing <info>payment stats</info> cache');

            $this->paymentsRepository->totalCount(true, true);
            $this->paymentsRepository->totalAmountSum(true, true);

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

            $this->averageMonthPayment->getAverageMonthPayment(true);
        }
    }

    public function registerRoutes(RouteList $router)
    {
        $router->addRoute('payments/return/gateway/<gatewayCode>', 'Payments:Return:gateway');
    }
}
