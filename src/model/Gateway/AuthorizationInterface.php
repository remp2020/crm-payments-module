<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Database\Table\ActiveRow;

interface AuthorizationInterface
{
    public function cancel(ActiveRow $payment): bool;

    public function getAuthorizationAmount(): float;
}
