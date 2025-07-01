<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;
use Tracy\Debugger;

trait PaypalRefundTrait
{
    public const REFUND_TRANSACTION_ID = 'refund_transaction_id';
    public const REFUND_AMOUNT = 'refund_amount';
    public const REFUND_DATE = 'refund_date';
    public const REFUND_CODE = 'refund_code';
    public const REFUND_MESSAGE = 'refund_message';

    public function refund(ActiveRow $payment, float $amount): RefundStatusEnum
    {
        $this->initialize();

        $transactionIdMeta = $this->paymentMetaRepository->findByPaymentAndKey($payment, 'transaction_id');
        if (!$transactionIdMeta) {
            return RefundStatusEnum::Failure;
        }

        $response = $this->gateway->refund([
            'amount' => $amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionReference' => $transactionIdMeta->value,
        ])->send();
        $data = $response->getData();

        if (!$response->isSuccessful()) {
            Debugger::log($response->getMessage(), Debugger::ERROR);

            if (isset($data['L_ERRORCODE0'])) {
                $this->paymentMetaRepository->add(
                    payment: $payment,
                    key: self::REFUND_CODE,
                    value: $data['L_ERRORCODE0'],
                );
            }

            if (isset($data['L_LONGMESSAGE0'])) {
                $this->paymentMetaRepository->add(
                    payment: $payment,
                    key: self::REFUND_MESSAGE,
                    value: $data['L_LONGMESSAGE0'],
                );
            }

            return RefundStatusEnum::Failure;
        }

        if (isset($data['REFUNDTRANSACTIONID'])) {
            $this->paymentMetaRepository->add(
                payment: $payment,
                key: self::REFUND_TRANSACTION_ID,
                value: $data['REFUNDTRANSACTIONID'],
            );
        }
        $this->paymentMetaRepository->add(
            payment: $payment,
            key: self::REFUND_AMOUNT,
            value: $amount,
        );
        $this->paymentMetaRepository->add(
            payment: $payment,
            key: self::REFUND_DATE,
            value: (new \DateTime)->format(DATE_RFC3339),
        );

        return RefundStatusEnum::Success;
    }
}
