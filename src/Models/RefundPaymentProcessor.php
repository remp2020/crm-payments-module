<?php

namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Nette\Database\Table\ActiveRow;

class RefundPaymentProcessor
{
    public const string REFUND_AMOUNT_META_KEY = 'refund_amount';

    public function __construct(
        private readonly PaymentMetaRepository $paymentMetaRepository,
    ) {
    }

    public function processRefundedPayment(
        ActiveRow $payment,
        float $amount,
    ): ActiveRow {
        $this->paymentMetaRepository->add($payment, self::REFUND_AMOUNT_META_KEY, $amount);

        // Future implementation:
        // - create child payment
        // - link both payments and their payment items
        // - return created payment
        return $payment;
    }
}
