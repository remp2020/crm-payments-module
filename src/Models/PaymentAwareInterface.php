<?php

namespace Crm\PaymentsModule;

use Nette\Database\Table\ActiveRow;

interface PaymentAwareInterface
{
    public function getPayment(): ?ActiveRow;
}
