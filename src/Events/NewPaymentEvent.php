<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewPaymentEvent extends AbstractEvent implements PaymentEventInterface
{
    public function __construct(private ActiveRow $payment)
    {
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }
}
