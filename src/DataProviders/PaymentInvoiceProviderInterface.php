<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\InvoicesModule\InvoiceGenerationException;
use Crm\InvoicesModule\PaymentNotInvoiceableException;
use Nette\Database\Table\ActiveRow;

interface PaymentInvoiceProviderInterface
{
    /**
     * @param ActiveRow $payment
     * @throws InvoiceGenerationException
     * @throws PaymentNotInvoiceableException If payment is not invoiceable from valid reasons (eg. outside of invoiceable period).
     */
    public function provide(ActiveRow $payment);
}
