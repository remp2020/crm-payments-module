<?php

namespace Crm\PaymentsModule\Components\PaymentToSubscriptionMenu;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

/**
 * This widgets renders subscription edit link for specific payment.
 *
 * @package Crm\SubscriptionsModule\Components
 */
class PaymentToSubscriptionMenu extends BaseLazyWidget
{
    private $templateName = 'payment_to_subscription_menu.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private PaymentsRepository $paymentsRepository,
    ) {
        parent::__construct($widgetManager);
    }

    public function header()
    {
        return 'PaymentToSubscription';
    }

    public function identifier()
    {
        return 'paymenttosubscriptionmenu';
    }

    public function render($subscription)
    {
        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if (!$payment) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }
}
