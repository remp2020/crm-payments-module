<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\Payment\RenewalPayment;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;

class AttachRenewalPaymentEventHandler extends AbstractListener
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly PaymentGatewaysRepository $paymentGatewaysRepository,
        private readonly ContentAccessRepository $contentAccessRepository,
        private readonly UsersRepository $usersRepository,
        private readonly Emitter $emitter,
        private readonly OneStopShop $oneStopShop,
        private readonly RenewalPayment $renewalPayment,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof AttachRenewalPaymentEvent)) {
            throw new \Exception('Invalid type of event `' . get_class($event) . '` received, expected: `CreateNewPaymentEvent`');
        }

        $subscriptionId = $event->getSubscriptionId();
        $userId = $event->getUserId();

        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if ($subscription === null) {
            throw new \Exception("Subscription ID: `{$subscriptionId}` not found.");
        }

        $user = $this->usersRepository->find($userId);
        if ($user === null) {
            throw new \Exception("User ID: `{$userId}` not found.");
        }

        $renewalPayment = $this->renewalPayment->getRenewalPayment($subscription)
            ?? $this->createRenewalPayment($subscription, $user);

        if (!isset($renewalPayment)) {
            return;
        }

        $this->renewalPayment->setRenewalPayment($subscription, $renewalPayment);

        // attach new payment to fired event
        $event->setAdditionalJobParameters([
            'renewal_payment_id' => $renewalPayment->id,
        ]);
    }

    private function createRenewalPayment($subscription, $user): ?ActiveRow
    {
        // find default subscription with sames length and content access
        $contentAccesses = $this->contentAccessRepository->allForSubscriptionType($subscription->subscription_type)->fetchPairs('name', 'name');
        $newSubscriptionType = $this->subscriptionTypesRepository->findDefaultForLengthAndContentAccesses($subscription->length, ...$contentAccesses);

        // check for subscription types next_subscription_type_id
        if ($newSubscriptionType === null) {
            $newSubscriptionType = $subscription->subscription_type->next_subscription_type;
        }

        if ($newSubscriptionType === null) {
            $contentAccesses = implode(', ', $contentAccesses);
            throw new \Exception("Next subscription type for content accesses: {$contentAccesses} not found.");
        }

        // create new payment
        $paymentGatewayCode = BankTransfer::GATEWAY_CODE;
        $paymentGateway = $this->paymentGatewaysRepository->findByCode($paymentGatewayCode);
        if ($paymentGateway === null) {
            throw new \Exception("Payment gateway `{$paymentGatewayCode}` not found.");
        }

        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($newSubscriptionType));

        // allow creating new payment outside of payments module
        $creatingNewPaymentEvent = new BeforeCreateRenewalPaymentEvent(
            $subscription->subscription_type,
            $paymentGateway,
            $user,
            $paymentItemContainer,
            [],
        );
        $this->emitter->emit($creatingNewPaymentEvent);
        if ($creatingNewPaymentEvent->shouldPreventCreatingRenewalPayment()) {
            return null;
        }
        $newPayment = $creatingNewPaymentEvent->getRenewalPayment();

        if ($newPayment === null) {
            $countryResolution = $this->oneStopShop->resolveCountry(
                user: $user,
                paymentItemContainer: $paymentItemContainer,
                // use previous payment if found
                previousPayment: $subscription->related('payments')->fetch()
            );

            // we failed, try to resolve naturally, but expect the "default" because this is not an online action
            if (!$countryResolution) {
                // try to resolve based on the previous payment
                $sourcePayment = $this->paymentsRepository->subscriptionPayment($subscription);
                if ($sourcePayment && $sourcePayment->payment_country_id) {
                    $countryResolution = new CountryResolution(
                        country: $sourcePayment->payment_country,
                        reason: $sourcePayment->payment_country_resolution_reason,
                    );
                }
            }

            $newPayment = $this->paymentsRepository->add(
                subscriptionType: $newSubscriptionType,
                paymentGateway: $paymentGateway,
                user: $user,
                paymentItemContainer: $paymentItemContainer,
                paymentCountry: $countryResolution?->country,
                paymentCountryResolutionReason: $countryResolution?->getReasonValue(),
            );
        }

        return $newPayment;
    }
}
