<?php

namespace Crm\PaymentsModule;

use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class RecurrentPaymentsResolver
{
    protected $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $subscriptionTypesRepository;

    public $lastFailedChargeAt = null;

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

    /**
     * resolveFailedRecurrent checks following recurring payments after charge failed and returns last recurring payment.
     *
     * This method follows sequence:
     * - get $recurrentPayment->payment
     * - get $payment->recurringPayment (via parent_payment_id)
     *
     * So methods always follows original payment and it doesn't jump to recurrent payment of other subscription.
     *
     * If resolved is unable to find following recurring profile, it returns same payment.
     */
    public function resolveFailedRecurrent(ActiveRow $recurrentPayment): ActiveRow
    {
        if ($recurrentPayment->state === RecurrentPaymentsRepository::STATE_CHARGE_FAILED) {
            $this->lastFailedChargeAt = $recurrentPayment->charge_at;
            if (isset($recurrentPayment->payment) && ($nextRecurrent = $this->recurrentPaymentsRepository->recurrent($recurrentPayment->payment))) {
                $recurrentPayment = $this->resolveFailedRecurrent($nextRecurrent);
            } else {
                Debugger::log('Unable to find next payment for failed recurrent ID [' . $recurrentPayment->id . ']', Debugger::ERROR);
            }
        }
        return $recurrentPayment;
    }

    public function getLastChargeFailedDateTime(): DateTime
    {
        if ($this->lastFailedChargeAt === null) {
            throw new \Exception('No last charge_failed date is set. Did you call `resolveFailedRecurrent()`?');
        }

        return new DateTime($this->lastFailedChargeAt);
    }
}
