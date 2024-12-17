<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Models\Gateways\RecurrentAuthorizationInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;
use Tracy\ILogger;

class PaymentStatusChangeHandler extends AbstractListener
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly GatewayFactory $gatewayFactory,
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof PaymentEventInterface) {
            throw new \Exception("Invalid type of event received, 'PaymentEventInterface' expected: " . get_class($event));
        }

        $sendEmail = true;
        if ($event instanceof PaymentChangeStatusEvent) {
            $sendEmail = $event->getSendEmail();
        }

        $payment = $event->getPayment();
        // hard reload, other handlers could have alter the payment already
        $payment = $this->paymentsRepository->find($payment->id);

        if ($payment->status === PaymentStatusEnum::Refund->value) {
            $this->stopRecurrentPayment($payment);
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if ($payment->status === PaymentStatusEnum::Authorized->value && $gateway instanceof AuthorizationInterface) {
            // Change CID only for authorized payments using non-recurrent payment gateway. Recurrent authorizations
            // are be used to create new recurrent payment chains.
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

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (in_array($payment->status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid], true)
            || ($payment->status === PaymentStatusEnum::Authorized->value && $gateway instanceof RecurrentAuthorizationInterface)
        ) {
            $this->createSubscriptionFromPayment($payment, $sendEmail);
        }
    }

    /**
     * @return bool|int|ActiveRow
     */
    public function createSubscriptionFromPayment(ActiveRow $payment, bool $sendEmail, \DateTime $startTime = null, \DateTime $endTime = null)
    {
        if ($startTime === null && $payment->subscription_start_at) {
            if ($payment->subscription_start_at > $payment->paid_at) {
                $startTime = $payment->subscription_start_at;
            } else {
                $startTime = $payment->paid_at;
            }
        }
        if ($endTime === null && $payment->subscription_end_at) {
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
            $sendEmail,
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
        $newExternalToken = $payment->related('payment_meta')
            ->where('key', AuthorizationInterface::PAYMENT_META_CARD_ID)
            ->fetchField('value');

        $recurrentPaymentIdToUpdate = $payment->related('payment_meta')
            ->where('key', 'recurrent_payment_id_to_update_cid')
            ->fetchField('value');

        if (!$newExternalToken || !$recurrentPaymentIdToUpdate) {
            return;
        }

        $recurrentPaymentRow = $this->recurrentPaymentsRepository->find($recurrentPaymentIdToUpdate);
        if (!$recurrentPaymentRow) {
            Debugger::log("No related recurrent payment found for payment: {$payment->id} with meta `recurrent_payment_id_to_update_cid`: {$recurrentPaymentIdToUpdate}", ILogger::ERROR);
            return;
        }

        if ($recurrentPaymentRow->payment_method->external_token === $newExternalToken) {
            // Nothing to update. We'd just bloat the note field.
            return;
        }

        $note = "Changed external token from: {$recurrentPaymentRow->payment_method->external_token} to: $newExternalToken";
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $payment->user_id,
            $payment->payment_gateway_id,
            $newExternalToken,
        );
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, [
            'cid' => $newExternalToken,
            'payment_method_id' => $paymentMethod->id,
            'expires_at' => null,
            'note' => $recurrentPaymentRow->note ? $recurrentPaymentRow->note . ' ' . $note : $note,
        ]);
    }
}
