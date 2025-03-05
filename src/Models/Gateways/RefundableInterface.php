<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;

interface RefundableInterface
{
    public function refund(ActiveRow $payment, float $amount);
}
