<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;

/**
 * This widget fetches number of payments this/last month to date and renders line with
 * label and resulting values.
 *
 * @package Crm\PaymentsModule\Components
 */
class MonthToDateAmountStatWidget extends BaseWidget
{
    private $templateName = 'month_to_date_amount_stat_widget.latte';

    private $paymentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'monthtodateamountstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthAmount = $this->paymentsRepository->paidBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->sum('amount');
        $this->template->lastMonthDayAmount = $this->paymentsRepository->paidBetween(
            DateTime::from('first day of last month 00:00'),
            DateTime::from('-1 month')
        )->sum('amount');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
