<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\UsersModule\Repository\AddressesRepository;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Tracy\Debugger;
use Tracy\ILogger;

class PaymentStatusChangeHandler extends AbstractListener
{
    private $subscriptionsRepository;

    private $addressesRepository;

    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $emitter;

    public function __construct(
        SubscriptionsRepository $subscriptionsRepository,
        AddressesRepository $addressesRepository,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Emitter $emitter
    ) {
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->addressesRepository = $addressesRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        $payment = $event->getPayment();
        // hard reload, other handlers could have alter the payment already
        $payment = $this->paymentsRepository->find($payment->id);

        if (in_array($payment->status, [PaymentsRepository::STATUS_REFUND])) {
            $this->stopRecurrentPayment($payment);
        }

        if ($payment->status === PaymentsRepository::STATUS_AUTHORIZED) {
            $this->changeRecurrentPaymentCid($payment);
        }

        if ($payment->subscription_id) {
            return;
        }

        if (!$payment->subscription_type_id) {
            return;
        }

        if ($payment->subscription_type->no_subscription) {
            return;
        }

        if (in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID])) {
            $this->createSubscriptionFromPayment($payment, $event);
        }
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
            true,
            $payment->user,
            $subscriptionType,
            $startTime,
            $endTime,
            null,
            $address,
            true,
            $callbackBeforeNewSubscriptionEvent = function ($newSubscription) use ($payment) {
                $this->paymentsRepository->update($payment, ['subscription_id' => $newSubscription]);
            }
        );

        return $subscription;
    }

    private function stopRecurrentPayment(ActiveRow $payment)
    {
        $recurrent = $this->recurrentPaymentsRepository->recurrent($payment);
        if (!$recurrent || $recurrent->state !== RecurrentPaymentsRepository::STATE_ACTIVE) {
            return;
        }

        $this->recurrentPaymentsRepository->update($recurrent, [
            'state' => RecurrentPaymentsRepository::STATE_SYSTEM_STOP
        ]);
    }

    private function changeRecurrentPaymentCid(ActiveRow $payment)
    {
        $newCid = $payment->related('payment_meta')
            ->where('key', 'card_id')
            ->fetchField('value');

        $recurrentPaymentIdToUpdate = $payment->related('payment_meta')
            ->where('key', 'recurrent_payment_id_to_update_cid')
            ->fetchField('value');

        if (!$newCid || !$recurrentPaymentIdToUpdate) {
            return;
        }

        $recurrentPaymentRow = $this->recurrentPaymentsRepository->find($recurrentPaymentIdToUpdate);
        if (!$recurrentPaymentRow) {
            Debugger::log("No related recurrent payment found for payment: {$payment->id} with meta `recurrent_payment_id_to_update_cid`: {$recurrentPaymentIdToUpdate}", ILogger::ERROR);
            return;
        }

        $note = "Changed CID from: {$recurrentPaymentRow->cid} to: $newCid";
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, [
            'cid' => $newCid,
            'expires_at' => null,
            'note' => $recurrentPaymentRow->note ? $recurrentPaymentRow->note . ' ' . $note : $note,
        ]);
    }
}
