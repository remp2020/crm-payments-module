<?php

namespace Crm\PaymentsModule\Upgrade;

use Nette\Database\Table\ActiveRow;

class SubscriptionUpgrade
{
    private $message;

    private $canUpgrade;

    private $payment;

    private $subscription;

    private $availableUpgraders;

    private $paymentGateways = [];

    public function __construct(
        $message,
        $canUpgrade = false,
        ActiveRow $payment = null,
        ActiveRow $subscription = null,
        array $availableUpgraders = null,
        $paymentGateways = []
    ) {
        $this->message = $message;
        $this->canUpgrade = $canUpgrade;
        $this->payment = $payment;
        $this->subscription = $subscription;
        $this->availableUpgraders = $availableUpgraders;
        $this->paymentGateways = $paymentGateways;

        // precalculate charge prices based on actual data so they can be displayed in view before actual upgrade

        if (!empty($availableUpgraders)) {
            /** @var Upgrader $upgrader */
            foreach ($availableUpgraders as $upgrader) {
                $upgrader->calculateChargePrice($payment, $subscription);
            }
        }
    }

    public function canUpgrade()
    {
        return $this->canUpgrade;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getSubscription()
    {
        return $this->subscription;
    }

    /**
     * getNextSubscriptionType returns instance of SubscriptionType that will be used in the following charge of the
     * recurrent payment.
     *
     * @return mixed|ActiveRow
     */
    public function getNextSubscriptionType()
    {
        if ($this->subscription->subscription_type->next_subscription_type_id) {
            return $this->subscription->subscription_type->next_subscription_type;
        }

        return $this->subscription->subscription_type;
    }

    public function getAvailableUpgraders()
    {
        return $this->availableUpgraders;
    }

    public function getPaymentGateways()
    {
        return $this->paymentGateways;
    }
}
