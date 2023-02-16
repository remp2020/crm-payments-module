<?php

namespace Crm\PaymentsModule\Events;

use Nette\Database\Table\ActiveRow;

interface PaymentEventInterface
{
    public function getPayment(): ActiveRow;
}
