<?php

namespace Crm\PaymentsModule\Components\TotalUserPayments;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Models\AverageMonthPayment;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\UserStatsRepository;

/**
 * Display total amount spent by one user.
 *
 * This widget works correctly only if user's meta data are filled with calculated amounts.
 * See CalculateAveragesCommand - `payments:calculate_averages`. This command should be executed regularly (eg cron).
 */
class TotalUserPayments extends BaseLazyWidget
{
    private string $templateName = 'total_user_payments.latte';

    private PaymentsRepository $paymentsRepository;
    private UserStatsRepository $userStatsRepository;
    private AverageMonthPayment $averageMonthPayment;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository,
        AverageMonthPayment $averageMonthPayment,
        UserStatsRepository $userStatsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->averageMonthPayment = $averageMonthPayment;
        $this->userStatsRepository = $userStatsRepository;
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
            $stats = $this->userStatsRepository->userStats($userId);

            $this->template->stats = $stats;

            $this->template->subscriptionPaymentsAmount = $stats['subscription_payments_amount'] ?? 0;
            $this->template->subscriptionPayments = $stats['subscription_payments'] ?? 0;
            $this->template->avgMonthPayment = $stats['avg_month_payment'] ?? 0;

            $this->template->averageMonthSum = $this->averageMonthPayment->getAverageMonthPayment();
        }

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
