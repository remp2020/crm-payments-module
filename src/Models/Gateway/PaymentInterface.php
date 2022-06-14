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
     * Called after begin(), adds ability to return data from payment process.
     * @param $allowRedirect
     *
     * @return void|ProcessResponse
     */
    public function process($allowRedirect);

    /**
     * @param ActiveRow $payment
     * @return bool
     */
    public function complete($payment): ?bool;

    public function isSuccessful(): bool;
}
