<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\PaymentsModule\Repository\PaymentGiftCouponsRepository;
use Crm\SubscriptionsModule\DataProvider\FilterGiftedSubscriptionsDataProviderInterface;

class FilterGiftedSubscriptionsDataProvider implements FilterGiftedSubscriptionsDataProviderInterface
{
    private $paymentGiftCouponsRepository;

    public function __construct(PaymentGiftCouponsRepository $paymentGiftCouponsRepository)
    {
        $this->paymentGiftCouponsRepository = $paymentGiftCouponsRepository;
    }

    public function provide(array $subscriptionIDs): array
    {
        $giftSubscriptions = [];
        foreach ($this->paymentGiftCouponsRepository->findAllBySubscriptions($subscriptionIDs) as $giftCoupon) {
            $giftSubscriptions[$giftCoupon->subscription->id] = $giftCoupon->payment->user->email;
        }

        return $giftSubscriptions;
    }
}
