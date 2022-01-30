<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class NewPaymentItemEvent extends AbstractEvent
{
    private $paymentItem;

    public function __construct(ActiveRow $paymentItem)
    {
        $this->paymentItem = $paymentItem;
    }

    public function getPaymentItem(): ActiveRow
    {
        return $this->paymentItem;
    }
}
