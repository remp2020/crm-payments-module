<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BeforeBankTransferMailProcessingEvent extends AbstractEvent
{
    // Flag indicating whether payment should forcibly override/fix payment's gateway to bank transfer gateway.
    private bool $allowPaymentGatewayOverride = true;

    public function __construct(
        public readonly ActiveRow $payment,
    ) {
    }

    public function preventPaymentGatewayOverride(): void
    {
        $this->allowPaymentGatewayOverride = false;
    }

    public function isPaymentGatewayOverrideAllowed(): bool
    {
        return $this->allowPaymentGatewayOverride;
    }
}
