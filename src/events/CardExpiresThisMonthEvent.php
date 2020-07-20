<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\IRow;

class CardExpiresThisMonthEvent extends AbstractEvent
{
    private $recurrentPayment;

    private $hasUserNewRecurrent;

    public function __construct(IRow $recurrentPayment, bool $hasUserNewRecurrent)
    {
        $this->recurrentPayment = $recurrentPayment;
        $this->hasUserNewRecurrent = $hasUserNewRecurrent;
    }

    public function getRecurrentPayment(): IRow
    {
        return $this->recurrentPayment;
    }

    public function hasUserNewRecurrent(): bool
    {
        return $this->hasUserNewRecurrent;
    }
}
