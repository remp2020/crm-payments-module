<?php

namespace Crm\PaymentsModule\Events;

use Nette\Database\Table\ActiveRow;

interface PaymentItemEventInterface
{
    public function getPaymentItem(): ActiveRow;
}
