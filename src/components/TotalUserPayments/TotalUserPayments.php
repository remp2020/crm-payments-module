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

    public function identifier()
    {
        return 'usertotalspent';
    }

    public function render($userId)
    {
        $totalSum = $this->paymentsRepository->totalUserAmountSum($userId);
        $this->template->totalSum = $totalSum;

        if ($totalSum > 0) {
            $meta = $this->userMetaRepository->userMeta($userId);
            $this->template->meta = $meta;

            $average = $this->userMetaRepository->getTable()->select('AVG(value) AS average')->where(['key' => 'avg_month_payment'])->fetch();
            $this->template->averageMonthSum = $average->average;
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
