<?php

namespace Crm\PaymentsModule\DataProvider;

use Nette\Database\Table\ActiveRow;

interface PaymentInvoiceProviderInterface
{
    public function provide(ActiveRow $payment);
}
