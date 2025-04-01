<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\User\UserDataProviderInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

class PaymentsUserDataProvider implements UserDataProviderInterface
{
    private $paymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
    }

    public static function identifier(): string
    {
        return 'payments';
    }

    public function data($userId): ?array
    {
        return null;
    }

    public function download($userId)
    {
        $payments = $this->paymentsRepository->userPayments($userId)->where(['status != ?' => PaymentStatusEnum::Form->value]);

        $results = [];
        foreach ($payments as $payment) {
            $paidAt = $payment->paid_at ? $payment->paid_at->format(\DateTime::RFC3339) : null;
            // prepaid payments don't have paid_at date, load created_at instead
            if (is_null($paidAt) && $payment->status === PaymentStatusEnum::Prepaid->value) {
                $paidAt = $payment->created_at ? $payment->created_at->format(\DateTime::RFC3339) : null;
            }
            $result = [
                'variable_symbol' => $payment->variable_symbol,
                'amount' => $payment->amount,
                'subscription_type' => $payment->subscription_type ? $payment->subscription_type->user_label : null,
                'status' => $payment->status,
                'paid_at' => $paidAt,
                'ip' => $payment->ip,
            ];

            if ($payment->additional_amount > 0 && !is_null($payment->additional_type)) {
                // TODO: remove additional amount from amount?
                $result['additional_amount'] = $payment->additional_amount;
                $result['additional_type'] = $payment->additional_type;
            }

            $results[$payment->variable_symbol] = $result;
        }

        return $results;
    }

    public function downloadAttachments($userId)
    {
        return [];
    }

    public function protect($userId): array
    {
        return [];
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function delete($userId, $protectedData = [])
    {
        $payments = $this->paymentsRepository->userPayments($userId)->fetchAll();

        foreach ($payments as $payment) {
            $this->paymentsRepository->update($payment, [
                'ip' => 'GDPR removal',
                'user_agent' => 'GDPR removal',
            ]);
            $this->paymentsRepository->markAuditLogsForDelete($payment->getSignature());
        }

        return true;
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
