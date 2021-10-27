<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Components\IWidgetLegend;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * This widget fetches ending subscription without extension
 * for different time intervals and renders line with resulting values.
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
        Translator $translator
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
        $dateRanges = [
            'subscriptionsNotRenewedToday' => [
                'from' => DateTime::from('today 00:00:00'),
                'to' => DateTime::from('today 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.today'),
                'load' => true,
            ],
            'subscriptionsNotRenewedTomorow' => [
                'from' => DateTime::from('tomorrow 00:00:00'),
                'to' => DateTime::from('tomorrow 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.tomorrow'),
                'load' => true,
            ],
            'subscriptionsNotRenewedAfterTomorow' => [
                'from' => DateTime::from('+2 days 00:00:00'),
                'to' => DateTime::from('+2 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.after_tomorrow'),
                'load' => true,
            ],
            'subscriptionsNotRenewedInOneWeek' => [
                'from' => DateTime::from('today 00:00:00'),
                'to' => DateTime::from('+7 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.seven_days'),
                'load' => true,
            ],
            'subscriptionsNotRenewedInTwoWeeks' => [
                'from' => DateTime::from('today 00:00'),
                'to' => DateTime::from('+14 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.fourteen_days'),
                'load' => false,
            ],
            'subscriptionsNotRenewedInOneMonth' => [
                'from' =>  DateTime::from('today 00:00'),
                'to' => DateTime::from('+31 days 23:59:59'),
                'label' => $this->translator->translate('dashboard.time.thirtyone_days'),
                'load' => false,
            ],
        ];

        foreach ($dateRanges as $key => $dateRange) {
            if (!$dateRange['load']) {
                continue;
            }
            $this->template->$key = $this->paymentsRepository
                ->subscriptionsWithoutExtensionEndingBetweenCount(
                    $dateRange['from'],
                    $dateRange['to']
                );
        }

        // Following computations are cached since we want to speed up page loading
        $this->template->subscriptionsNotRenewedInTwoWeeks = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingNextTwoWeeksCount();
        $this->template->subscriptionsNotRenewedInOneMonth = $this->paymentsRepository
            ->subscriptionsWithoutExtensionEndingNextMonthCount();

        $this->template->dateRanges = $dateRanges;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
