<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Repository\UserMetaRepository;

/**
 * Display total amount spent by one user.
 *
 * This widget works correctly only if user's meta data are filled with calculated amounts.
 * See CalculateAveragesCommand - `payments:calculate_averages`. This command should be executed regularly (eg cron).
 */
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

            $this->template->subscriptionPaymentsAmount = $meta['subscription_payments_amount'] ?? 0;
            $this->template->subscriptionPayments = $meta['subscription_payments'] ?? 0;
            $this->template->avgMonthPayment = $meta['avg_month_payment'] ?? 0;

            $average = $this->userMetaRepository->getTable()->select('AVG(value) AS average')->where(['key' => 'avg_month_payment'])->fetch();
            $this->template->averageMonthSum = (float) $average->average;
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
