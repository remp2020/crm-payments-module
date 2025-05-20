<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class PaymentChangeStatusEvent extends AbstractEvent implements PaymentEventInterface
{
    public function __construct(
        private ActiveRow $payment,
        private bool $sendEmail = false,
    ) {
    }

    public function isPaid(): bool
    {
        return in_array($this->payment->status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value], true);
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }

    public function getSendEmail(): bool
    {
        return $this->sendEmail;
    }
}
