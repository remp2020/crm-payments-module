<?php

namespace Crm\PaymentsModule\Events;

use Nette\Database\Table\ActiveRow;

interface RecurrentPaymentEventInterface
{
    public function getRecurrentPayment(): ActiveRow;
}