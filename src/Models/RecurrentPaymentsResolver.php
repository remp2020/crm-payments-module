<?php

namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\RecurrentPaymentItemContainerReadyEvent;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionType;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainerFactory;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver\PaymentData;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

class RecurrentPaymentsResolver
{
    public $lastFailedChargeAt = null;

    public function __construct(
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private Translator $translator,
        private ApplicationConfig $applicationConfig,
        private Emitter $emitter,
        private OneStopShop $oneStopShop,
        private CountriesRepository $countriesRepository,
        private PaymentItemContainerFactory $paymentItemContainerFactory,
    ) {
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
        return $recurrentPayment->custom_amount;
    }

    /**
     * resolveChargeAmount calculates final amount of money to be charged next time, including
     * the standard subscription price.
     */
    public function resolveChargeAmount(ActiveRow $recurrentPayment): float
    {
        $paymentData = $this->resolvePaymentData($recurrentPayment);
        return $paymentData->customChargeAmount ?? $paymentData->paymentItemContainer->totalPrice();
    }

    public function resolveAddress(ActiveRow $recurrentPayment): ?ActiveRow
    {
        return $recurrentPayment->parent_payment->address ?? null;
    }

    /**
     * resolveFailedRecurrent checks following recurring payments after charge failed and returns last recurring
     * payment.
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

    private function createPaymentItemContainer(
        ActiveRow $recurrentPayment,
        ActiveRow $subscriptionType,
        ?float $customChargeAmount,
        null|float|int $additionalAmount,
        ?string $additionalType,
    ): PaymentItemContainer {
        $paymentItemContainer = new PaymentItemContainer();
        $parentPayment = $recurrentPayment->parent_payment;

        // we want to load previous payment items only if new subscription has same subscription type
        // and it isn't upgraded recurrent payment
        if ($subscriptionType->id === $parentPayment->subscription_type_id
            && $subscriptionType->id === $recurrentPayment->subscription_type_id
            && $parentPayment->amount === $recurrentPayment->subscription_type->price
            && !$customChargeAmount
        ) {
            $paymentItemContainer = $this->paymentItemContainerFactory->createFromPayment(
                $parentPayment,
                [SubscriptionTypePaymentItem::TYPE]
            );

            // In case of subscription type VAT change, parent payment would copy incorrect VAT rates
            // into the new payment items. If we see a change in a total price without VAT, we don't
            // copy the items anymore (the price with VAT was already checked in IF above).
            $containerToCompare = new PaymentItemContainer();
            $containerToCompare->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

            if (round($paymentItemContainer->totalPriceWithoutVAT(), 2) !==
                round($containerToCompare->totalPriceWithoutVAT(), 2)
            ) {
                $paymentItemContainer = $containerToCompare;
            }
        } elseif (!$customChargeAmount) {
            // if subscription type has changed or there is a price difference (e.g. caused by donation),
            // load subscription type payment items from subscription type
            $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        } else {
            $items = $this->getSubscriptionTypeItemsForCustomChargeAmount($subscriptionType, $customChargeAmount);
            $paymentItemContainer->addItems($items);
        }

        // Recurrent donation item are added directly
        if ($additionalType === 'recurrent' && $additionalAmount) {
            $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
            if ($donationPaymentVat === null) {
                throw new \RuntimeException("Config 'donation_vat_rate' is not set");
            }
            $paymentItemContainer->addItem(
                new DonationPaymentItem(
                    $this->translator->translate('payments.admin.donation'),
                    (float) $additionalAmount,
                    (int) $donationPaymentVat
                )
            );
        }

        // let modules add own items to PaymentItemContainer
        $this->emitter->emit(new RecurrentPaymentItemContainerReadyEvent(
            $paymentItemContainer,
            $recurrentPayment
        ));

        return $paymentItemContainer;
    }

    protected function getSubscriptionTypeItemsForCustomChargeAmount($subscriptionType, $customChargeAmount): array
    {
        $items = SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType);

        // sort items by vat (higher vat first)
        usort($items, static function (SubscriptionTypePaymentItem $a, SubscriptionTypePaymentItem $b) {
            return ($b->vat() <=> $a->vat());
        });

        // get vat-amount ratios, floor everything down to avoid scenario that everything is rounded up
        // and sum of items would be greater than charged amount
        $ratios = [];
        foreach ($items as $item) {
            $ratios[$item->vat()] = floor($item->unitPrice() / $subscriptionType->price * 100) / 100;
        }
        // any rounding weirdness (sum of ratios not being 1) should go in favor of higher vat (first item)
        $ratios[array_keys($ratios)[0]] += 1 - array_sum($ratios);

        // update prices based on found ratios
        $sum = 0;
        foreach ($items as $item) {
            $itemPrice = floor($customChargeAmount * $ratios[$item->vat()] * 100) / 100;
            $item->forcePrice($itemPrice);
            $sum += $itemPrice;
        }
        // any rounding weirdness (sum of items not being $customChargeamount) should go in favor of higher vat (first item)
        $items[0]->forcePrice(round($items[0]->unitPrice() + ($customChargeAmount - $sum), 2));

        $checkSum = 0;
        foreach ($items as $item) {
            $checkSum += $item->totalPrice();
        }
        if (round($checkSum, 2) !== round($customChargeAmount, 2)) {
            throw new \Exception("Cannot charge custom amount, sum of items [{$checkSum}] is different than charged amount [{$customChargeAmount}].");
        }

        return $items;
    }

    public function resolvePaymentData(ActiveRow $recurrentPayment, ?ActiveRow $forceSubscriptionType = null): PaymentData
    {
        $subscriptionType = $forceSubscriptionType ?? $this->resolveSubscriptionType($recurrentPayment);
        $customChargeAmount = $this->resolveCustomChargeAmount($recurrentPayment);
        $address = $this->resolveAddress($recurrentPayment);

        $additionalAmount = 0;
        $additionalType = null;
        $parentPayment = $recurrentPayment->parent_payment;
        if ($parentPayment && $parentPayment->additional_type === 'recurrent') {
            $additionalType = 'recurrent';
            $additionalAmount = $parentPayment->additional_amount;
        }

        $paymentItemContainer = $this->createPaymentItemContainer(
            $recurrentPayment,
            $subscriptionType,
            $customChargeAmount,
            $additionalAmount,
            $additionalType
        );

        // One Stop Shop
        $paymentCountry = null;
        $paymentCountryResolutionReason = null;
        if ($this->oneStopShop->isEnabled()) {
            $resolvedCountry = null;

            try {
                $resolvedCountry = $this->oneStopShop->resolveCountry(
                    user: $recurrentPayment->user,
                    paymentAddress: $address,
                    paymentItemContainer: $paymentItemContainer,
                    previousPayment: $parentPayment,
                );
            } catch (GeoIpException $e) {
                // Resolver probably tried to resolve the country based on "cli" IP address, will be handled further.
            }
            // IP address isn't a good indicator to resolve the country.
            // If there is no other previous resolution, IP of original payment should be used instead.
            if (!$resolvedCountry || $resolvedCountry->reason === CountryResolutionType::IP_ADDRESS) {
                $originalPaymentIp = $this->resolveOriginalPaymentIp($parentPayment);
                if ($originalPaymentIp) {
                    $resolvedCountry = $this->oneStopShop->resolveCountry(
                        user: $recurrentPayment->user,
                        paymentAddress: $address,
                        paymentItemContainer: $paymentItemContainer,
                        ipAddress: $originalPaymentIp,
                        previousPayment: $parentPayment,
                    );
                }
            }

            if ($resolvedCountry) {
                $paymentCountry = $this->countriesRepository->findByIsoCode($resolvedCountry?->countryCode);
                $paymentCountryResolutionReason = $resolvedCountry->getReasonValue();
            }
        }

        return new PaymentData(
            $paymentItemContainer,
            $subscriptionType,
            $customChargeAmount,
            $additionalAmount,
            $additionalType,
            $address,
            $paymentCountry,
            $paymentCountryResolutionReason
        );
    }

    private function resolveOriginalPaymentIp(ActiveRow $payment): ?string
    {
        if ($payment->recurrent_charge) {
            $recurrentPayment = $this->recurrentPaymentsRepository->findByPayment($payment);
            if (!$recurrentPayment) {
                // failsafe for inconsistent data
                return null;
            }

            return $this->resolveOriginalPaymentIp($recurrentPayment->parent_payment);
        }

        if ($payment->ip === 'cli') {
            return null;
        }

        return $payment->ip;
    }
}
