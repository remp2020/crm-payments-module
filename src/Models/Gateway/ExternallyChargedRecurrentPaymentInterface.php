<?php

namespace Crm\PaymentsModule\Gateways;

/**
 * ExternallyChargedRecurrentPaymentInterface identifies recurrent payment gateways
 * which don't provide control over process of recurrent charging and charge user
 * automatically based on the initial subscription configuration.
 */
interface ExternallyChargedRecurrentPaymentInterface
{
    /**
     * getChargedPaymentStatus identifies the payment.status field of charged payment.
     * Recommended values to return:
     *
     *   - PaymentsRepository::STATUS_PAID: Return if the money are charged by external service to your account -
     *     meaning you have the money immediately after the charge (e.g. Paypal)
     *   - PaymentsRepository::STATUS_PREPAID: Return if the money are collected by external service and invoiced
     *     to you separately (e.g. inapp iTunes payments)
     *
     * @return string
     */
    public function getChargedPaymentStatus(): string;

    /**
     * getSubscriptionExpiration returns end time of charged subscription. This date meant to be used as:
     *
     *  - `charge_at` field for recurrent payments, so the next payment is verified around the next billing cycle.
     *  - `subscription.end_time` for subscriptions, so user's subscription end time matches with end time provided
     *    by payment gateway service provider.
     *
     * Parameter $cid is supposed to hint the correct subscription in case your gateway provider always returns multiple
     * active subscriptions.
     */
    public function getSubscriptionExpiration(string $cid = null): \DateTime;
}
