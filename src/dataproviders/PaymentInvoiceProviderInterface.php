<?php

namespace Crm\PaymentsModule\DataProvider;

use Nette\Database\Table\ActiveRow;

interface PaymentInvoiceProviderInterface
{
    /**
     * @param ActiveRow $payment
     * @throws \Crm\InvoicesModule\InvoiceGenerationException
     * @throws \Crm\InvoicesModule\PaymentNotInvoiceableException If payment is not invoiceable from valid reasons (eg. outside of invoiceable period).
     */
    public function provide(ActiveRow $payment);
}
