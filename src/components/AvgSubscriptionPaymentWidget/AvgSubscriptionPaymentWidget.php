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

    public function render(array $userIds)
    {
        if (!count($userIds)) {
            return;
        }

        $result = $this->userMetaRepository
            ->getTable()
            ->select('COALESCE(SUM(value), 0) AS sum')
            ->where(['key' => 'subscription_payments', 'user_id' => $userIds])
            ->fetch();

        $this->template->avgSubscriptionPayments = $result->sum / count($userIds);
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
