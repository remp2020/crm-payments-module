<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Gateways;

/**
 * Interface for externally charged gateways that support stopping/canceling
 * subscriptions programmatically.
 *
 * Gateways implementing ExternallyChargedRecurrentPaymentInterface are typically
 * not stoppable because the billing is managed externally. However, some providers
 * (like Stripe Billing) expose APIs to cancel subscriptions.
 *
 */
interface StoppableExternallyChargedRecurrentPaymentInterface extends ExternallyChargedRecurrentPaymentInterface
{
    /**
     * Cancels the external subscription associated with the given token.
     *
     * @param string $token The recurrent token (e.g., Stripe subscription ID)
     * @return bool True if cancellation was successful
     * @throws \Exception If cancellation fails
     */
    public function cancelExternalSubscription(string $token): bool;
}
