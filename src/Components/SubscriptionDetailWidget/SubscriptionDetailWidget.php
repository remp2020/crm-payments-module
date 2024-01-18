<?php

namespace Crm\PaymentsModule\Components\SubscriptionDetailWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Database\Table\ActiveRow;

class SubscriptionDetailWidget extends BaseLazyWidget
{
    private $templateName = 'subscription_detail_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private PaymentsRepository $paymentsRepository,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier()
    {
        return 'subscriptiondetailwidget';
    }

    public function render(ActiveRow $subscription)
    {
        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment === null) {
            return;
        }
        $this->template->payment = $payment;

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
