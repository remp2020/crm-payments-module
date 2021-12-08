<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;

class BeforeRecurrentPaymentChargeEvent extends AbstractEvent
{
    private $payment;

    private $token;

    /**
     * Same parameters that go to RecurrentPaymentInterface#charge()
     *
     * @param $payment
     * @param $token
     */
    public function __construct($payment, $token)
    {
        $this->payment = $payment;
        $this->token = $token;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getToken()
    {
        return $this->token;
    }
}
