<?php

namespace Crm\PaymentsModule\User;

use Crm\ApplicationModule\User\UserDataProviderInterface;
use Crm\InvoicesModule\InvoiceGenerator;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class PaymentsUserDataProvider implements UserDataProviderInterface
{
    private $paymentsRepository;

    private $invoiceGenerator;

    public function __construct(PaymentsRepository $paymentsRepository, InvoiceGenerator $invoiceGenerator)
    {
        $this->paymentsRepository = $paymentsRepository;
        $this->invoiceGenerator = $invoiceGenerator;
    }

    public static function identifier(): string
    {
        return 'payments';
    }

    public function data($userId)
    {
        return [];
    }

    // TODO: orders
    public function download($userId)
    {
        $payments = $this->paymentsRepository->userPayments($userId)->where(['status != ?' => PaymentsRepository::STATUS_FORM]);

        $results = [];
        foreach ($payments as $payment) {
            $paidAt = $payment->paid_at ? $payment->paid_at->format(\DateTime::RFC3339) : null;
            // prepaid payments don't have paid_at date, load created_at instead
            if (is_null($paidAt) && $payment->status === PaymentsRepository::STATUS_PREPAID) {
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
        $payments = $this->paymentsRepository->userPayments($userId)->where('invoice_id IS NOT NULL');

        $files = [];
        foreach ($payments as $payment) {
            $invoiceFile = tempnam(sys_get_temp_dir(), 'invoice');
            $this->invoiceGenerator->renderInvoicePDFToFile($invoiceFile, $payment->user, $payment);
            $fileName = $payment->invoice->invoice_number->number . '.pdf';
            $files[$fileName] = $invoiceFile;
        }

        return $files;
    }

    public function protect($userId): array
    {
        return [];
    }

    /**
     * @param $userId
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
        }

        return true;
    }

    public function canBeDeleted($userId): array
    {
        return [true, null];
    }
}
