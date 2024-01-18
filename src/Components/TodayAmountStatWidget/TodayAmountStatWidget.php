<?php

namespace Crm\PaymentsModule\Components\TodayAmountStatWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches amount of today payments and renders line with
 * label and resulting value.
 *
 * @package Crm\PaymentsModule\Components
 */
class TodayAmountStatWidget extends BaseLazyWidget
{
    private $templateName = 'today_amount_stat_widget.latte';

    private $paymentsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'todayamountstatwidget';
    }

    public function render()
    {
        $this->template->todayAmount = $this->paymentsRepository->paidBetween(
            DateTime::from('today 00:00'),
            new DateTime()
        )->sum('amount') ?? 0.00;
        $this->template->yesterdayAmount = $this->paymentsRepository->paidBetween(
            DateTime::from('yesterday 00:00'),
            DateTime::from('today 00:00')
        )->sum('amount') ?? 0.00;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
