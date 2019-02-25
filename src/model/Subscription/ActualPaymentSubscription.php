<?php

namespace Crm\PaymentsModule\Subscription;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Upgrade\Expander;
use Crm\SubscriptionsModule\Subscription\ActualUserSubscription;

class ActualPaymentSubscription
{
    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $expander;

    private $actualUserSubscription;

    private $payment = false;

    private $isRecurrent = false;

    private $recurrent = false;

    public function __construct(
        ActualUserSubscription $actualUserSubscription,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Expander $expander
    ) {
        $this->actualUserSubscription = $actualUserSubscription;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->expander = $expander;
    }

    private function init()
    {
        $actualRecurrentSubscription = $this->actualUserSubscription->getActualRecurrentSubscription();
        if (!$actualRecurrentSubscription) {
            return;
        }
        $this->payment = $this->paymentsRepository->subscriptionPayment($actualRecurrentSubscription);
        if (!$this->payment) {
            $this->payment = $this->paymentsRepository->subscriptionPayment($this->actualUserSubscription->getSubscription());
        }
        if ($this->payment) {
            $this->isRecurrent = $this->payment->payment_gateway->is_recurrent ? true : false;

            if ($this->isRecurrent) {
                $this->recurrent = $this->recurrentPaymentsRepository->recurrent($this->payment);
            }
        }
    }

    public function getPayment()
    {
        $this->init();
        return $this->payment;
    }

    public function isRecurrent()
    {
        $this->init();
        return $this->isRecurrent;
    }

    public function isActiveRecurrent()
    {
        $this->init();
        if ($this->recurrent) {
            return $this->recurrent->state == RecurrentPaymentsRepository::STATE_ACTIVE;
        }
        return false;
    }

    public function getRecurrent()
    {
        $this->init();
        return $this->recurrent;
    }
}
