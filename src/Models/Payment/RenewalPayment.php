<?php

namespace Crm\PaymentsModule\Models\Payment;

use Crm\PaymentsModule\Events\SubscriptionRenewalPaymentAttachedEvent;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;

class RenewalPayment
{
    public const RENEWAL_PAYMENT_META_KEY = 'renewal_payment_id';

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
        private readonly Emitter $emitter,
    ) {
    }

    public function getRenewalPayment(ActiveRow $subscription): ?ActiveRow
    {
        $subscriptionMeta = $this->subscriptionMetaRepository
            ->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)
            ->fetch();

        return $subscriptionMeta ? $this->paymentsRepository->find($subscriptionMeta->value) : null;
    }

    public function attachRenewalPayment(ActiveRow $subscription, ActiveRow $renewalPayment): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::RENEWAL_PAYMENT_META_KEY, $renewalPayment->id);

        $this->emitter->emit(new SubscriptionRenewalPaymentAttachedEvent(
            renewalPayment: $renewalPayment,
            subscription: $subscription,
        ));
    }

    public function unsetRenewalPayment(ActiveRow $subscription): void
    {
        $this->subscriptionMetaRepository->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)->delete();
    }

    public function getSubscriptionByRenewalPayment(ActiveRow $renewalPayment): ?ActiveRow
    {
        return $this->subscriptionMetaRepository->findSubscriptionBy(self::RENEWAL_PAYMENT_META_KEY, $renewalPayment->id);
    }
}
