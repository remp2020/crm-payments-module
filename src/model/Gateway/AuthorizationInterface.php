<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Database\Table\IRow;

interface AuthorizationInterface
{
    public function cancel(IRow $payment): bool;

    public function getAuthorizationAmount(): float;
}
