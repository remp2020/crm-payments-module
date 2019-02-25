<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;

abstract class BaseRecurrentPaymentEvent extends AbstractEvent
{
    private $recurrentPayment;

    public function __construct($recurrentPayment)
    {
        $this->recurrentPayment = $recurrentPayment;
    }

    public function getRecurrentPayment()
    {
        return $this->recurrentPayment;
    }
}
