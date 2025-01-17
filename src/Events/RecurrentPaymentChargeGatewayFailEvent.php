<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\GatewayFail;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentChargeGatewayFailEvent extends AbstractEvent implements RecurrentPaymentEventInterface
{
    private bool $failedRecurrentPaymentProcessed = false;

    public function __construct(
        private readonly ActiveRow $recurrentPayment,
        private readonly GatewayFail $exception,
    ) {
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }

    public function getException(): GatewayFail
    {
        return $this->exception;
    }

    // Set this to true if your event handler processed the failed recurrent, and you want to prevent the execution
    // of the default handler which creates next recurrent_payment.
    public function setFailedRecurrentPaymentProcessed(bool $failedRecurrentPaymentProcessed): void
    {
        $this->failedRecurrentPaymentProcessed = $failedRecurrentPaymentProcessed;
    }

    public function isFailedRecurrentPaymentProcessed(): bool
    {
        return $this->failedRecurrentPaymentProcessed;
    }
}
