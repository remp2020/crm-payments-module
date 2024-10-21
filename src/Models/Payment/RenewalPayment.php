<?php

namespace Crm\PaymentsModule\Models\Payment;

use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Nette\Database\Table\ActiveRow;

class RenewalPayment
{
    public const RENEWAL_PAYMENT_META_KEY = 'renewal_payment_id';

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
    ) {
    }

    public function getRenewalPayment(ActiveRow $subscription): ?ActiveRow
    {
        $subscriptionMeta = $this->subscriptionMetaRepository
            ->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)
            ->fetch();

        return $subscriptionMeta ? $this->paymentsRepository->find($subscriptionMeta->value) : null;
    }

    public function setRenewalPayment(ActiveRow $subscription, ActiveRow $renewalPayment): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::RENEWAL_PAYMENT_META_KEY, $renewalPayment->id);
    }

    public function setRenewalPaymentId(ActiveRow $subscription, int $renewalPaymentId): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::RENEWAL_PAYMENT_META_KEY, $renewalPaymentId);
    }

    public function unsetRenewalPayment(ActiveRow $subscription): void
    {
        $this->subscriptionMetaRepository->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)->delete();
    }
}
