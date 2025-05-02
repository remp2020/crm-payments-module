<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class PaymentMethodCopiedEvent extends AbstractEvent
{
    private readonly ActiveRow $sourcePaymentMethod;
    private readonly ActiveRow $newPaymentMethod;

    public function __construct(ActiveRow $sourcePaymentMethod, ActiveRow $newPaymentMethod)
    {
        $this->sourcePaymentMethod = $sourcePaymentMethod;
        $this->newPaymentMethod = $newPaymentMethod;
    }

    public function getSourcePaymentMethod(): ActiveRow
    {
        return $this->sourcePaymentMethod;
    }

    public function getNewPaymentMethod(): ActiveRow
    {
        return $this->newPaymentMethod;
    }
}
