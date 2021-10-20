<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Database\Table\ActiveRow;

interface PaymentInterface
{
    /**
     * @param ActiveRow $payment
     */
    public function begin($payment);

    /**
     * @param ActiveRow $payment
     * @return bool
     */
    public function complete($payment): ?bool;


    public function isSuccessful(): bool;
}
