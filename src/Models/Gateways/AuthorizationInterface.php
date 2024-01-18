<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;

interface AuthorizationInterface
{
    public function cancel(ActiveRow $payment): bool;

    public function getAuthorizationAmount(): float;
}
