<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use League\Event\AbstractEvent;

class PaymentChangeStatusEvent extends AbstractEvent
{
    private $payment;

    private $sendEmail = false;

    public function __construct($payment, $sendEmail = false)
    {
        $this->payment = $payment;
        $this->sendEmail = $sendEmail;
    }

    public function isPaid()
    {
        return $this->payment->status === PaymentsRepository::STATUS_PAID;
    }

    public function getPayment()
    {
        return $this->payment;
    }

    public function getSendEmail()
    {
        return $this->sendEmail;
    }
}
