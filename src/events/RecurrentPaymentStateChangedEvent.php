<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class RecurrentPaymentStateChangedEvent extends AbstractEvent
{
    private $recurrentPayment;

    public function __construct(IRow $recurrentPayment)
    {
        $this->recurrentPayment = $recurrentPayment;
    }

    public function getRecurrentPayment(): IRow
    {
        return $this->recurrentPayment;
    }
}
