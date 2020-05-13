<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\UsersModule\Repository\UserMetaRepository;

class AvgSubscriptionPaymentWidget extends BaseWidget
{
    private $templateName = 'avg_subscription_payment_widget.latte';

    private $userMetaRepository;

    public function __construct(WidgetManager $widgetManager, UserMetaRepository $userMetaRepository)
    {
        parent::__construct($widgetManager);
        $this->userMetaRepository = $userMetaRepository;
    }

    public function identifier()
    {
        return 'avgsubscriptionpaymentwidget';
    }

    public function render(array $usersIds)
    {
        if (count($usersIds)) {
            $usersIds = implode(',', $usersIds);
            $average = $this->userMetaRepository
                ->getTable()
                ->select('AVG(value) AS avg_subscription_payment')
                ->where(['key' => 'paid_payments', 'user_id' => $usersIds])
                ->fetch();

            $this->template->avgSubscriptionPayments = $average->avg_subscription_payment;

            $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
            $this->template->render();
        }
    }
}
