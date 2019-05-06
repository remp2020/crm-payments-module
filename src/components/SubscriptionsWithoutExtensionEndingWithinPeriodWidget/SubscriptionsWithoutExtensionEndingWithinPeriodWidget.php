<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Components\IWidgetLegend;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;

/**
 * Dashboard widget showing line of stats of users with ending subscription
 * for different time intervals.
 *
 * @package Crm\PaymentsModule\Components
 */
class SubscriptionsWithoutExtensionEndingWithinPeriodWidget extends BaseWidget implements IWidgetLegend
{
    private $templateName = 'subscriptions_without_extension_ending_within_period.latte';

    private $paymentsRepository;

    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository,
        ITranslator $translator
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'subscriptionswithoutextensionendingwithinperiod';
    }

    public function legend(): string
    {
        return sprintf('<span class="text-danger">%s</span>', $this->translator->translate('dashboard.subscriptions.ending.nonext.title'));
    }

    public function render()
    {
        $this->template->subscriptionsNotRenewedToday = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingBetweenCount(
                DateTime::from('today 00:00'),
                DateTime::from('today 23:59:59')
            );
        $this->template->subscriptionsNotRenewedTomorow = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingBetweenCount(
                DateTime::from('tomorrow 00:00'),
                DateTime::from('tomorrow 23:59:59')
            );
        $this->template->subscriptionsNotRenewedAfterTomorow = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingBetweenCount(
                DateTime::from('+2 days 00:00'),
                DateTime::from('+2 days 23:59:59')
            );
        $this->template->subscriptionsNotRenewedInOneWeek = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingBetweenCount(
                DateTime::from('today 00:00'),
                DateTime::from('+7 days 23:59:59')
            );
        // Following computations are cached since we want to speed up page loading
        $this->template->subscriptionsNotRenewedInTwoWeeks = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingNextTwoWeeksCount();
        $this->template->subscriptionsNotRenewedInOneMonth = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingNextMonthCount();

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
