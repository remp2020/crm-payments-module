<?php

namespace Crm\PaymentsModule\Components\SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Components\WidgetLegendInterface;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * This widget fetches ending subscription with active uncharged recurrent payment
 * for different time intervals and renders line wwith resulting values.
 *
 * @package Crm\PaymentsModule\Components
 */
class SubscriptionsWithActiveUnchargedRecurrentEndingWithinPeriodWidget extends BaseLazyWidget implements WidgetLegendInterface
{
    private $templateName = 'subscriptions_with_active_uncharged_recurrent_ending_within_period.latte';

    private $paymentsRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
    }

    public function legend(): string
    {
        return sprintf('<span class="text-primary">%s</span>', $this->translator->translate('dashboard.subscriptions.ending.withrecurrent.title'));
    }

    public function identifier()
    {
        return 'subscriptionswithactiveunchargedrecurrentendingwithinperiod';
    }

    public function render()
    {
        $this->template->subscriptionsAutoRenewToday = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingBetween(DateTime::from('today 00:00'), DateTime::from('today 23:59:59'))
            ->count('*');
        $this->template->subscriptionsAutoRenewTomorow = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingBetween(DateTime::from('tomorrow 00:00'), DateTime::from('tomorrow 23:59:59'))
            ->count('*');
        $this->template->subscriptionsAutoRenewAfterTomorow = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingBetween(DateTime::from('+2 days 00:00'), DateTime::from('+2 days 23:59:59'))
            ->count('*');
        $this->template->subscriptionsAutoRenewInOneWeek = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingBetween(DateTime::from('today 00:00'), DateTime::from('+7 days 23:59:59'))
            ->count('*');

        // Cached values for wide intervals to speed up dashboard loading
        $this->template->subscriptionsAutoRenewInTwoWeeks = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingNextTwoWeeksCount();
        $this->template->subscriptionsAutoRenewInOneMonth = $this->paymentsRepository
            ->subscriptionsWithActiveUnchargedRecurrentEndingNextMonthCount();

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
