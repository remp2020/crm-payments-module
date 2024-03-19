<?php

namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;

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
     *
     * Returns:
     * - $recurrent_payment.subscription_type
     *   - If $subscription_type.trial_periods or $subscription_type.next_subscription_type are NOT set.
     * - $recurrent_payment.subscription_type.next_subscription_type
     *   - If $subscription_type.next_subscription_type is set
     *     AND number of used trials is same or greater than $subscription_type.trial_periods.
     * - $recurrent_payment.next_subscription_type
     *   - If this is set. This is override of trial periods set on subscription type.
     * - $recurrent_payment.next_subscription_type.next_subscription_type
     *   - If this is set. This is override of trial periods set on subscription type.
     *   - Note: This is kept, so we don't introduce breaking change. Until now, it worked this way in case
     *           helpdesk manually set next subscription type to trial type. We wanted to skip second trial.
     */
    public function resolveSubscriptionType(ActiveRow $recurrentPayment): ActiveRow
    {
        // if override is set directly on recurrent payment, return it
        if ($recurrentPayment->next_subscription_type_id) {
            // TODO: consider removing this in the future (breaking change), it looks ridiculous
            if ($recurrentPayment->next_subscription_type->next_subscription_type_id) {
                return $recurrentPayment->next_subscription_type->next_subscription_type;
            }
            return $recurrentPayment->next_subscription_type;
        }

        /** @var ActiveRow $subscriptionType */
        $subscriptionType = $this->subscriptionTypesRepository->find($recurrentPayment->subscription_type_id);

        // next subscription OR trial periods NOT set; return current subscription type
        if ($subscriptionType->next_subscription_type_id === null || $subscriptionType->trial_periods === 0) {
            return $subscriptionType;
        }

        // next_subscription_type_id is SET and there is single trial period (which was used by payment
        // which created this recurrent) => return next subscription type
        if ($subscriptionType->trial_periods === 1) {
            return $subscriptionType->next_subscription_type;
        }

        $trialPeriodsUsed = 1; // $recurrentPayment already implies one used period
        $previousRecurrentCharge = $recurrentPayment;
        while ($previousRecurrentCharge) {
            // $previousRecurrentCharge might be failed attempt, we need to find the latest successful attempt before continuing
            $previousRecurrentCharge = $this->recurrentPaymentsRepository->latestSuccessfulRecurrentPayment($previousRecurrentCharge);
            if (!$previousRecurrentCharge) {
                break;
            }
            $previousRecurrentCharge = $this->recurrentPaymentsRepository->findByPayment($previousRecurrentCharge->parent_payment);
            if (!$previousRecurrentCharge) {
                break;
            }
            $trialPeriodsUsed += 1;
        }

        // return next non-trial subscription if user used all trials
        // minus 1 because we are now creating recurrent payment which will affect next subscription
        if ($trialPeriodsUsed >= $subscriptionType->trial_periods) {
            return $subscriptionType->next_subscription_type;
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
            $this->lastFailedChargeAt = $recurrentPayment->payment->created_at;

            $nextRecurrent = $this->recurrentPaymentsRepository->recurrent($recurrentPayment->payment);
            $recurrentPayment = $this->resolveFailedRecurrent($nextRecurrent);
        }
        if ($recurrentPayment->state === RecurrentPaymentsRepository::STATE_SYSTEM_STOP && $recurrentPayment->payment_id) {
            $nextRecurrent = $this->recurrentPaymentsRepository->recurrent($recurrentPayment->payment);
            if ($nextRecurrent) {
                // In case of reactivation scenario, there might be following recurrent payment even when there was
                // a "system stop" state. Let's check if it's there and if it is, continue traversing deeper.
                $this->lastFailedChargeAt = $recurrentPayment->payment->created_at;
                $recurrentPayment = $this->resolveFailedRecurrent($nextRecurrent);
            }
        }
        return $recurrentPayment;
    }

    public function getLastFailedChargeDateTime(): DateTime
    {
        if ($this->lastFailedChargeAt === null) {
            throw new \Exception('No last charge_failed date is set. Did you call `resolveFailedRecurrent()`?');
        }

        return new DateTime($this->lastFailedChargeAt);
    }
}
