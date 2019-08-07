<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Database\Table\IRow;

interface PaymentInterface
{
    /**
     * @param IRow $payment
     */
    public function begin($payment);

    /**
     * @param IRow $payment
     * @return bool
     */
    public function complete($payment): ?bool;


    public function isSuccessful(): bool;
}
