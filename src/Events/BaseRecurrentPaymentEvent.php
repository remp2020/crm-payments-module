<?php

namespace Crm\PaymentsModule\Events;

use Crm\UsersModule\Models\User\IUserGetter;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

abstract class BaseRecurrentPaymentEvent extends AbstractEvent implements IUserGetter, RecurrentPaymentEventInterface
{
    public function __construct(private ActiveRow $recurrentPayment)
    {
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }

    public function getUserId(): int
    {
        return $this->recurrentPayment->user_id;
    }
}
