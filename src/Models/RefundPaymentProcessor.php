<?php

namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Database\Table\ActiveRow;

class RefundPaymentProcessor
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentsRepository $paymentsRepository,
    ) {
    }

    public function processRefundedPayment(
        ActiveRow $payment,
        float $amount,
    ): ActiveRow {
        // create child payment
        // link both payments

        return $payment;
    }
}
