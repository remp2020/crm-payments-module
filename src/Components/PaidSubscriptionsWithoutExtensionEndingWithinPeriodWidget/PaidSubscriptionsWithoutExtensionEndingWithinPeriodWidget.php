<?php

namespace Crm\PaymentsModule\Components\PaidSubscriptionsWithoutExtensionEndingWithinPeriodWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Components\WidgetLegendInterface;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * This widget fetches ending subscription without extension
 * fot different time intervals and renders line with resulting values.
 *
 * @package Crm\PaymentsModule\Components
 */
class PaidSubscriptionsWithoutExtensionEndingWithinPeriodWidget extends BaseLazyWidget implements WidgetLegendInterface
{
    private $templateName = 'paid_subscriptions_without_extension_ending_within_period.latte';

    private $paymentsRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository,
        Translator $translator,
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
    }

    public function identifier()
    {
        return 'paidsubscriptionswithoutextensionendingwithinperiod';
    }

    public function legend(): string
    {
        return sprintf('<span style="color: #CD4E4B;">%s</span>', $this->translator->translate('dashboard.subscriptions.ending.nonext_paid.title'));
    }

    public function render()
    {
        $dateRanges = [
            'subscriptionsNotRenewedToday' => [
                'from' => DateTime::from('today 00:00:00'),
                'to' => DateTime::from('today 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.today'),
            ],
            'subscriptionsNotRenewedTomorow' => [
                'from' => DateTime::from('tomorrow 00:00:00'),
                'to' => DateTime::from('tomorrow 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.tomorrow'),
            ],
            'subscriptionsNotRenewedAfterTomorow' => [
                'from' => DateTime::from('+2 days 00:00:00'),
                'to' => DateTime::from('+2 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.after_tomorrow'),
            ],
            'subscriptionsNotRenewedInOneWeek' => [
                'from' => DateTime::from('today 00:00:00'),
                'to' => DateTime::from('+7 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.seven_days'),
            ],
            // Following computations are cached since we want to speed up page loading
            'subscriptionsNotRenewedInTwoWeeks' => [
                'from' => DateTime::from('today 00:00'),
                'to' => DateTime::from('+14 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.fourteen_days'),
                'callable' => fn () => $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextTwoWeeksCount(false, true),
            ],
            'subscriptionsNotRenewedInOneMonth' => [
                'from' =>  DateTime::from('today 00:00'),
                'to' => DateTime::from('+31 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.thirtyone_days'),
                'callable' => fn () => $this->paymentsRepository->subscriptionsWithoutExtensionEndingNextMonthCount(false, true),
            ],
        ];

        foreach ($dateRanges as $key => $dateRange) {
            if (isset($dateRange['callable'])) {
                $this->template->stats[$key] = $dateRange['callable']();
            } else {
                $this->template->stats[$key] = $this->paymentsRepository
                    ->subscriptionsWithoutExtensionEndingBetweenCount(
                        $dateRange['from'],
                        $dateRange['to'],
                        true,
                    );
            }
        }

        $this->template->dateRanges = $dateRanges;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
