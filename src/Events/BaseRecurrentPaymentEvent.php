<?php

namespace Crm\PaymentsModule\Events;

use Crm\UsersModule\User\IUserGetter;
use League\Event\AbstractEvent;

abstract class BaseRecurrentPaymentEvent extends AbstractEvent implements IUserGetter
{
    private $recurrentPayment;

    public function __construct($recurrentPayment)
    {
        $this->recurrentPayment = $recurrentPayment;
    }

    public function getRecurrentPayment()
    {
        return $this->recurrentPayment;
    }

    public function getUserId(): int
    {
        return $this->recurrentPayment->user_id;
    }
}
