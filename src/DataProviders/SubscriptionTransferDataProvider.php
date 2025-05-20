<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\DataProviders\SubscriptionTransferDataProviderInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;

class SubscriptionTransferDataProvider implements SubscriptionTransferDataProviderInterface
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function provide(array $params): void
    {
    }

    public function transfer(ActiveRow $subscription, ActiveRow $userToTransferTo, ArrayHash $formData): void
    {
        if (!$this->isTransferable($subscription)) {
            // this should never happen, as a back-end should check transferability before calling providers
            throw new DataProviderException('Subscription is not transferable');
        }

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment === null) {
            return;
        }

        $this->transferSubscriptionsPayment($payment, $userToTransferTo);
        $this->transferSubscriptionsRecurrentPayment($payment, $userToTransferTo);
    }

    public function isTransferable(ActiveRow $subscription): bool
    {
        if ($this->isPaymentRenewed($subscription)) {
            return false;
        }

        return true;
    }

    private function transferSubscriptionsPayment(ActiveRow $payment, ActiveRow $userToTransferTo): void
    {
        $this->paymentsRepository->addMeta($payment, [
            SubscriptionTransferDataProviderInterface::META_KEY_TRANSFERRED_FROM_USER => $payment->user_id,
        ]);

        $this->paymentsRepository->update(
            $payment,
            ['user_id' => $userToTransferTo->id],
        );
    }

    private function transferSubscriptionsRecurrentPayment(ActiveRow $payment, ActiveRow $userToTransferTo): void
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        if (!$recurrentPayment) {
            return;
        }

        // Create copy of payment method. Multiple recurrents can use the same payment method.
        $paymentMethod = $this->paymentMethodsRepository->copyPaymentMethodToUser(
            sourcePaymentMethod: $recurrentPayment->payment_method,
            user: $userToTransferTo,
        );

        $this->recurrentPaymentsRepository->update(
            $recurrentPayment,
            [
                'user_id' => $userToTransferTo->id,
                'payment_method_id' => $paymentMethod->id,
            ],
        );
    }

    private function isPaymentRenewed(ActiveRow $subscription): bool
    {
        if ($subscription->start_time > new DateTime()) {
            return false;
        }

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment === null) {
            return false;
        }

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        if ($recurrentPayment === null) {
            return false;
        }

        return $recurrentPayment->state === RecurrentPaymentStateEnum::Charged->value;
    }
}
