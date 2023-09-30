<?php

namespace Crm\PaymentsModule\Gateways;

interface RecurrentPaymentInterface
{
    /**
     * CHARGE_OK should be returned from charge() method if it was processed synchronously, successful and caller
     * is safe trigger payment confirmation process and related flows.
     */
    public const CHARGE_OK = 'charge_ok';

    /**
     * CHARGE_PENDING should be returned from charge() method if it was processed asynchronously and the charge request
     * was triggered successfully. Status indicates that payment will be confirmed in a separate flow and it shouldn't
     * be attempted to be charged again unless the pending attempt expires and fails.
     *
     * It's responsibility of gateway to expire/timeout the pending state and fail if the payment isn't confirmed
     * within reasonable time. We recommend to emit delayed hermes event right before the `pending` state is returned.
     */
    public const CHARGE_PENDING = 'charge_pending';

    /**
     * Charge initiates and (if possible) charges user amount of money based on the provided payment.
     *
     * If charge fails, method can return one of three exceptions:
     *   - GatewayFail. Returned if there's error in communication with payment gateway (e.g invalid API key, network error).
     *   - RecurrentPaymentFailTry. Returned if bank didn't accept the charge (e.g insufficient funds).
     *   - RecurrentPaymentFailStop. Same scenario as "fail try", but no further attempts should be made for the recurring payment.
     *
     * @param $payment
     * @param string $token
     * @return string One of CHARGE_* constants defined at `RecurrentPaymentInterface`
     *
     * @throws \Crm\PaymentsModule\GatewayFail
     * @throws \Crm\PaymentsModule\RecurrentPaymentFailStop
     * @throws \Crm\PaymentsModule\RecurrentPaymentFailTry
     */
    public function charge($payment, $token): string;

    /**
     * @param string $token
     * @return boolean
     */
    public function checkValid($token);

    /**
     * Returns array [$cid => (DateTime)$expiration]
     *
     * @param string[] $recurrentPayments
     * @return array
     */
    public function checkExpire($recurrentPayments);

    /**
     * @return bool
     */
    public function hasRecurrentToken(): bool;

    /**
     * @return string
     */
    public function getRecurrentToken();

    public function getResultCode(): ?string;

    public function getResultMessage(): ?string;
}
