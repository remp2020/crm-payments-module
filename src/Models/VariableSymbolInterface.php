<?php

namespace Crm\PaymentsModule\Models;

use Nette\Database\Table\ActiveRow;

interface VariableSymbolInterface
{
    public function getNew(?ActiveRow $paymentGateway): string;
}
