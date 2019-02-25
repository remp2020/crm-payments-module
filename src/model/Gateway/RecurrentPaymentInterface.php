<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Database\IRow;

interface RecurrentPaymentInterface
{
    /**
     * @param string $token
     * @return boolean
     */
    public function checkValid($token);

    /**
     * Returns array [$cid => (DateTime)$expiration]
     *
     * @param IRow[] $recurrentPayments
     * @return array
     */
    public function checkExpire($recurrentPayments);

    /**
     * @param $payment
     * @param string $token
     * @throws \Crm\PaymentsModule\RecurrentPaymentFailStop
     * @throws \Crm\PaymentsModule\RecurrentPaymentFailTry
     */
    public function charge($payment, $token);

    /**
     * @return bool
     */
    public function hasRecurrentToken(): bool;

    /**
     * @return string
     */
    public function getRecurrentToken();

    /**
     * @return string
     */
    public function getResultCode();

    /**
     * @return string
     */
    public function getResultMessage();
}
