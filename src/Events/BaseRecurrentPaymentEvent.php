<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use Crm\UsersModule\Events\UserEventInterface;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

abstract class BaseRecurrentPaymentEvent extends AbstractEvent implements UserEventInterface, RecurrentPaymentEventInterface
{
    public function __construct(
        private ActiveRow $recurrentPayment,
    ) {
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }

    public function getUser(): ActiveRow
    {
        return $this->recurrentPayment->user;
    }
}
