<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class TotalAmountStatWidget extends BaseWidget
{
    private $templateName = 'total_amount_stat_widget.latte';

    private $paymentsRepository;

    public function __construct(WidgetManager $widgetManager, PaymentsRepository $paymentsRepository)
    {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'totalamountstatwidget';
    }

    public function render()
    {
        $this->template->totalAmount = $this->paymentsRepository->totalAmountSum(true);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
