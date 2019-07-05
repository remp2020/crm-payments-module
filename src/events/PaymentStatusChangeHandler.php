<?php

namespace Crm\PaymentsModule\Events;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Events\NewSubscriptionEvent;
use Crm\SubscriptionsModule\Events\SubscriptionEndsEvent;
use Crm\SubscriptionsModule\Events\SubscriptionStartsEvent;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\PaymentsModule\Upgrade\Expander;
use Crm\UsersModule\Repository\AddressesRepository;
use DateTime;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;

class PaymentStatusChangeHandler extends AbstractListener
{
    private $subscriptionsRepository;

    private $addressesRepository;

    private $paymentsRepository;

    private $emitter;

    private $hermesEmitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        AddressesRepository $addressesRepository,
        PaymentsRepository $paymentsRepository,
        Emitter $emitter,
        \Tomaj\Hermes\Emitter $hermesEmitter
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->addressesRepository = $addressesRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->emitter = $emitter;
        $this->hermesEmitter = $hermesEmitter;
    }

    public function handle(EventInterface $event)
    {
        $payment = $event->getPayment();

        if ($payment->subscription_id) {
            return false;
        }

        if (!$payment->subscription_type_id) {
            return false;
        }

        if ($payment->subscription_type->type == SubscriptionTypesRepository::TYPE_PRODUCT) {
            return false;
        }

        if (in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID]) && !$payment->subscription_type->no_subscription) {
            if (in_array($payment->upgrade_type, [Expander::UPGRADE_PAID_EXTEND, Expander::UPGRADE_SPECIAL, Expander::UPGRADE_RECURRENT])) {
                $subscription = $this->upgradeSubscriptionFromPayment($payment, $event);
            } else {
                $subscription = $this->createSubscriptionFromPayment($payment, $event);
            }
            return $subscription;
        }

        return true;
    }

    /**
     * @param ActiveRow|IRow $payment
     * @param EventInterface $event
     *
     * @return bool|int|IRow
     */
    private function createSubscriptionFromPayment(ActiveRow $payment, EventInterface $event)
    {
        $startTime = null;
        $endTime = null;

        if ($payment->subscription_start_at) {
            if ($payment->subscription_start_at > $payment->paid_at) {
                $startTime = $payment->subscription_start_at;
            } else {
                $startTime = $payment->paid_at;
            }
        }
        if ($payment->subscription_end_at) {
            $endTime = $payment->subscription_end_at;
        }

        $address = null;
        if ($payment->address_id) {
            $address = $payment->address;
        }

        $subscriptionType = SubscriptionsRepository::TYPE_REGULAR;
        if ($payment->status === PaymentsRepository::STATUS_PREPAID) {
            $subscriptionType = SubscriptionsRepository::TYPE_PREPAID;
        }

        $subscription = $this->subscriptionsRepository->add(
            $payment->subscription_type,
            $payment->payment_gateway->is_recurrent,
            $payment->user,
            $subscriptionType,
            $startTime,
            $endTime,
            null,
            $address
        );
        $this->paymentsRepository->update($payment, ['subscription_id' => $subscription]);

        if ($subscription->end_time <= new DateTime()) {
            $this->subscriptionsRepository->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_AFTER_END]);
            $this->emitter->emit(new SubscriptionEndsEvent($subscription));
        } elseif ($subscription->start_time <= new DateTime()) {
            $this->subscriptionsRepository->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_ACTIVE]);
            $this->emitter->emit(new SubscriptionStartsEvent($subscription));
        } else {
            $this->subscriptionsRepository->update($subscription, ['internal_status' => SubscriptionsRepository::INTERNAL_STATUS_BEFORE_START]);
        }

        return $subscription;
    }

    public function upgradeSubscriptionFromPayment($payment, $event)
    {
        $actualSubscription = $this->subscriptionsRepository->actualUserSubscription($payment->user->id);
        if (!$actualSubscription) {
            // subscription ended since upgrade was requested, create new subscription
            return $this->createSubscriptionFromPayment($payment, $event);
        }

        $changeTime = new DateTime();

        $originalEndTime = $actualSubscription->end_time;

        $this->subscriptionsRepository->update($actualSubscription, [
            'end_time' => $changeTime,
            'internal_status' => SubscriptionsRepository::INTERNAL_STATUS_AFTER_END,
            'note' => '[upgrade] povodne koncilo ' . $actualSubscription->end_time,
        ]);
        $actualUserSubscription = $this->subscriptionsRepository->find(($actualSubscription->id));
        $this->emitter->emit(new SubscriptionEndsEvent($actualUserSubscription));

        $newSubscription = $this->subscriptionsRepository->add(
            $payment->subscription_type,
            $payment->payment_gateway->is_recurrent,
            $payment->user,
            SubscriptionsRepository::TYPE_UPGRADE,
            $changeTime
        );
        $this->subscriptionsRepository->update($newSubscription, [
            'internal_status' => SubscriptionsRepository::INTERNAL_STATUS_ACTIVE,
            'note' => "Upgrade z {$actualUserSubscription->subscription_type->name} na {$payment->subscription_type->name}",
        ]);
        if ($payment->upgrade_type == Expander::UPGRADE_SPECIAL || $payment->upgrade_type == Expander::UPGRADE_RECURRENT) {
            $this->subscriptionsRepository->update($newSubscription, [
                'end_time' => $originalEndTime,
            ]);
        }
        $this->paymentsRepository->update($payment, ['subscription_id' => $newSubscription]);
        $this->subscriptionsRepository->update($actualSubscription, ['next_subscription_id' => $newSubscription->id]);

        $this->emitter->emit(new SubscriptionStartsEvent($newSubscription));

        return $newSubscription;
    }
}
