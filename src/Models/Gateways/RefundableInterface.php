<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;

interface RefundableInterface
{
    /**
     * Refund should return the selected amount of money from the payment to user through the payment option they
     * used for their original payment. In case of successful execution, it RefundStatusEnum::Success.
     */
    public function refund(ActiveRow $payment, float $amount): RefundStatusEnum;
}
