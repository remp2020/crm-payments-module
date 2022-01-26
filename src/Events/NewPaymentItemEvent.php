<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class NewPaymentItemEvent extends AbstractEvent
{
    private $paymentItem;

    public function __construct(IRow $paymentItem)
    {
        $this->paymentItem = $paymentItem;
    }

    public function getPaymentItem(): IRow
    {
        return $this->paymentItem;
    }
}
