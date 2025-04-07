<?php

namespace Crm\PaymentsModule\Models\Payment;

use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\RespektCzModule\Models\Renewal\DiscountLevel;
use Crm\RespektCzModule\Models\Renewal\DiscountLevelEnum;
use Crm\RespektCzModule\Models\Renewal\SubscriptionCategory;
use Crm\RespektCzModule\Models\Renewal\SubscriptionCategoryHandler;
use Crm\RespektCzModule\Models\Renewal\SubscriptionGroupEnum;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Nette\Database\Table\ActiveRow;

class RenewalPayment
{
    public const RENEWAL_PAYMENT_META_KEY = 'renewal_payment_id';

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
        private readonly DiscountLevel $discountLevel,
        private readonly PaymentMetaRepository $paymentMetaRepository,
        private readonly SubscriptionCategoryHandler $subscriptionCategoryHandler,
    ) {
    }

    public function getRenewalPayment(ActiveRow $subscription): ?ActiveRow
    {
        $subscriptionMeta = $this->subscriptionMetaRepository
            ->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)
            ->fetch();

        return $subscriptionMeta ? $this->paymentsRepository->find($subscriptionMeta->value) : null;
    }

    /***
     * Subscription group -> copy from subscription
     * Discount level -> assign based on renewal payment subscription type
     */
    public function attachRenewalPayment(ActiveRow $subscription, ActiveRow $renewalPayment): void
    {
        $this->subscriptionMetaRepository->setMeta($subscription, self::RENEWAL_PAYMENT_META_KEY, $renewalPayment->id);

        $subscriptionCategory = $this->subscriptionCategoryHandler->getPaymentSubscriptionCategory($renewalPayment);
        if ($subscriptionCategory) {
            return;
        }

        $subscriptionTypeDiscountLevel = $this->discountLevel->getDiscountLevel($renewalPayment->subscription_type);
        $renewalPaymentCategory = SubscriptionCategory::fromValues(
            $subscription->respektcz_subscription_group ?? SubscriptionGroupEnum::getDefaultEnum()->value,
            $subscriptionTypeDiscountLevel?->value ?? DiscountLevelEnum::getDefaultEnum()->value,
        );

        foreach ($renewalPaymentCategory->prepareMeta() as $key => $value) {
            $this->paymentMetaRepository->add($renewalPayment, $key, $value);
        }
    }

    public function unsetRenewalPayment(ActiveRow $subscription): void
    {
        $this->subscriptionMetaRepository->getMeta($subscription, self::RENEWAL_PAYMENT_META_KEY)->delete();
    }
}
