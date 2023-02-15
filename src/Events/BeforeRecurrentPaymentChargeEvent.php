<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BeforeRecurrentPaymentChargeEvent extends AbstractEvent implements PaymentEventInterface
{
    /**
     * Same parameters that go to RecurrentPaymentInterface#charge()
     */
    public function __construct(
        private ActiveRow $payment,
        private string $token
    ) {
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
