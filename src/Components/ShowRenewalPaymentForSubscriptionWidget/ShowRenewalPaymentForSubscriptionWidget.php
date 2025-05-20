<?php

namespace Crm\PaymentsModule\Components\ShowRenewalPaymentForSubscriptionWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Models\Payment\RenewalPayment;
use Nette\Database\Table\ActiveRow;

class ShowRenewalPaymentForSubscriptionWidget extends BaseLazyWidget
{
    private $templateName = 'show_renewal_payment_for_subscription_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly RenewalPayment $renewalPayment,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier(): string
    {
        return 'showrenewalpaymentforsubscription';
    }

    public function render(ActiveRow $subscription): void
    {
        if ($subscription->is_recurrent) {
            return;
        }

        if ($subscription->end_time < (new \DateTime())->modify('-90 days')) {
            return;
        }

        $payment = $this->renewalPayment->getRenewalPayment($subscription);
        if (!$payment) {
            return;
        }

        $this->template->renewalPayment = $payment;

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
