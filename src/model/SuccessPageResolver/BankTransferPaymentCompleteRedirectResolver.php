<?php

namespace Crm\PaymentsModule;

use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class BankTransferPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if ($payment && $payment->payment_gateway->code === 'bank_transfer') {
            return true;
        }
        return false;
    }

    public function redirectArgs(?ActiveRow $payment, string $status): array
    {
        if (!$payment || $payment->payment_gateway->code !== 'bank_transfer') {
            throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
        }

        return [
            ':Payments:BankTransfer:info',
            [
                'id' => $payment->variable_symbol,
            ],
        ];
    }
}
