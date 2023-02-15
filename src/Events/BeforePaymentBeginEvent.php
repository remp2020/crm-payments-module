<?php

namespace Crm\PaymentsModule\Events;

use Crm\ApplicationModule\ActiveRow;
use League\Event\AbstractEvent;

class BeforePaymentBeginEvent extends AbstractEvent implements PaymentEventInterface
{
    public function __construct(private ActiveRow $payment)
    {
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }
}
