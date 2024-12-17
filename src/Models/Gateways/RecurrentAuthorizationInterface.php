<?php

namespace Crm\PaymentsModule\Models\Gateways;

/**
 * RecurrentAuthorizationInterface gateways are expected to:
 *
 *  1) allow authorization requests to obtain payment method tokens to execute recurrent charges (similar
 *     to AuthorizationInterface), and
 *  2) create new recurrent payment with obtained payment method token.
 *
 * Use these gateways for zero-amount trials with future recurrent charge scenarios.
 *
 * This interface allows modules creating payments to determine whether they want to allow zero  payment or not.
 * By default, zero payments are not allowed; if you want to allow it on a specific place, you need
 * to configure PaymentItemContainer to allowZeroPayments().
 */
interface RecurrentAuthorizationInterface
{
    public const PAYMENT_META_CARD_ID = 'card_id';
    public const PAYMENT_META_CARD_NUMBER = 'card_number';

    /**
     * getRecurrentPaymentGatewayCode provides code of the payment gateway that should be used to execute subsequent
     * recurrent charges made with the payment method token.
     *
     * @return string
     */
    public function getAuthorizedRecurrentPaymentGatewayCode(): string;
}
