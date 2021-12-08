<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;

class BeforePaymentBeginEvent extends AbstractEvent
{
    private $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function getPayment()
    {
        return $this->payment;
    }
}
