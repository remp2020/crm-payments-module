<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentStoppedByUserEvent extends AbstractEvent
{
    private $recurrentPayment;

    public function __construct(ActiveRow $recurrentPayment)
    {
        $this->recurrentPayment = $recurrentPayment;
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }
}
