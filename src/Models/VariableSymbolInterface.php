<?php

namespace Crm\PaymentsModule;

use Nette\Database\Table\ActiveRow;

interface VariableSymbolInterface
{
    public function getNew(?ActiveRow $paymentGateway): string;
}
