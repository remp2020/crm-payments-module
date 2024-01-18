<?php

namespace Crm\PaymentsModule\Models;

use Nette\Database\Table\ActiveRow;

interface PaymentAwareInterface
{
    public function getPayment(): ?ActiveRow;
}
