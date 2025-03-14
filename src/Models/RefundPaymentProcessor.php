<?php

namespace Crm\PaymentsModule\Models;

use Nette\Database\Table\ActiveRow;

class RefundPaymentProcessor
{
    public function __construct()
    {
    }

    public function processRefundedPayment(
        ActiveRow $payment,
        float $amount,
    ): ActiveRow {
        // Future implementation:
        // - create child payment
        // - link both payments and their payment items
        // - return created payment
        return $payment;
    }
}
