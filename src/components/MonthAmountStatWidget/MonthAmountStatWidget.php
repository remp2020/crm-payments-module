<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Utils\DateTime;

class MonthAmountStatWidget extends BaseWidget
{
    private $templateName = 'month_amount_stat_widget.latte';

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
        return 'monthamountstatwidget';
    }

    public function render()
    {
        $this->template->thisMonthAmount = $this->paymentsRepository->paidBetween(
            DateTime::from(date('Y-m')),
            new DateTime()
        )->sum('amount');
        $this->template->lastMonthAmount = $this->paymentsRepository->paidBetween(
            DateTime::from('first day of -1 month 00:00'),
            DateTime::from(date('Y-m'))
        )->sum('amount');
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
