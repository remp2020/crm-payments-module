<?php

namespace Crm\PaymentsModule;

use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentsResolver
{
    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $subscriptionTypesRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    /**
     * resolveSubscriptionType determines which subscriptionType will be used within the next charge.
     */
    public function resolveSubscriptionType(ActiveRow $recurrentPayment): ActiveRow
    {
        $subscriptionType = $this->subscriptionTypesRepository->find($recurrentPayment->subscription_type_id);
        if ($recurrentPayment->next_subscription_type_id) {
            $subscriptionType = $this->subscriptionTypesRepository->find($recurrentPayment->next_subscription_type_id);
        }
        if ($subscriptionType->next_subscription_type_id) {
            $subscriptionType = $this->subscriptionTypesRepository->find($subscriptionType->next_subscription_type_id);
        }
        return $subscriptionType;
    }

    /**
     * resolveCustomChargeAmount calculates only non-standard charge amount which can be used
     * as "amount" parameter in PaymentsRepository::add().
     */
    public function resolveCustomChargeAmount(ActiveRow $recurrentPayment) : ?float
    {
        $amount = null;
        if ($recurrentPayment->custom_amount != null) {
            $amount = $recurrentPayment->custom_amount;
        }
        return $amount;
    }

    /**
     * resolveChargeAmount calculates final amount of money to be charged next time, including
     * the standard subscription price.
     */
    public function resolveChargeAmount(ActiveRow $recurrentPayment) : float
    {
        $subscriptionType = $this->resolveSubscriptionType($recurrentPayment);
        $amount = $subscriptionType->price;
        if ($recurrentPayment->custom_amount != null) {
            $amount = $recurrentPayment->custom_amount;
        } elseif ($amount != $recurrentPayment->parent_payment->amount) {
            // original payment could contain recurring donations
            foreach ($this->paymentsRepository->getPaymentItems($recurrentPayment->parent_payment) as $paymentItem) {
                if ($paymentItem['type'] === DonationPaymentItem::TYPE
                        && $recurrentPayment->parent_payment->additional_type == 'recurrent'
                    ) {
                    $amount += $paymentItem['amount'] * $paymentItem['count'];
                }
            }
        }
        return $amount;
    }
}
