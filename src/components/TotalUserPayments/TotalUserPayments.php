<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;

class TotalUserPayments extends BaseWidget
{
    private $templateName = 'total_user_payments.latte';

    private $paymentsRepository;

    private $userMetaRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository,
        UserMetaRepository $userMetaRepository
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->userMetaRepository = $userMetaRepository;
    }

    public function header($id = '')
    {
        return 'Celkovo minul';
    }

    public function identifier()
    {
        return 'usertotalspent';
    }

    public function render($id)
    {
        $totalSum = $this->paymentsRepository->totalUserAmountSum($id);
        $this->template->totalSum = $totalSum;

        if ($totalSum > 0) {
            $meta = $this->userMetaRepository->userMeta($id);
            $this->template->meta = $meta;

            $average = $this->userMetaRepository->getTable()->select('AVG(value) AS average')->where(['key' => 'avg_month_payment'])->fetch();
            $this->template->averageMonthSum = $average->average;
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
