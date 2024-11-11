<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Tomaj\Hermes\Emitter;

class RecurrentPaymentsUserDataProvider implements UserDataProviderInterface
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly Emitter $hermesEmitter,
    ) {
    }

    public static function identifier(): string
    {
        return 'recurrent_payments';
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        return [];
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    public function delete($userId, $protectedData = [])
    {
        $this->recurrentPaymentsRepository->stoppedByGDPR($userId);

        $allPaymentMethods = $this->paymentMethodsRepository->findAllForUser($userId);
        foreach ($allPaymentMethods as $paymentMethod) {
            // anonymize payment method cid-s
            $this->paymentMethodsRepository->update($paymentMethod, [
                'external_token' => 'GDPR removal ' . $paymentMethod->id,
            ]);

            // anonymize all cid-s in `recurrent_payments` table
            $this->recurrentPaymentsRepository->getTable()->where([
                'user_id' => $userId,
                'payment_method_id' => $paymentMethod->id,
            ])->update([
                'cid' => 'GDPR removal ' . $paymentMethod->id
            ]);

            // fire `external_token` anonymized hermes event
            $this->hermesEmitter->emit(new HermesMessage('payment-method-anonymized-external-token', [
                'payment_method_id' => $paymentMethod->id,
                'external_token' => $paymentMethod->external_token,
            ]), HermesMessage::PRIORITY_LOW);
        }
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
