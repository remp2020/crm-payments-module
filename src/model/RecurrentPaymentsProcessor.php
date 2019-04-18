<?php

namespace Crm\PaymentsModule;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;

class RecurrentPaymentsProcessor
{
    private $recurrentPaymentsRepository;

    private $paymentsRepository;

    private $paymentLogsRepository;

    private $applicationConfig;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        PaymentLogsRepository $paymentLogsRepository,
        ApplicationConfig $applicationConfig
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentLogsRepository = $paymentLogsRepository;
        $this->applicationConfig = $applicationConfig;
    }

    public function startRecurrentUsingCid($payment, $cid, RecurrentPaymentInterface $gateway): bool
    {
        try {
            $gateway->charge($payment, $cid);
        } catch (\Exception $e) {
            $this->paymentsRepository->updateStatus(
                $payment,
                PaymentsRepository::STATUS_FAIL,
                false,
                $payment->note . '; failed: ' . $gateway->getResultCode()
            );
        }

        $this->paymentLogsRepository->add(
            $gateway->isSuccessful() ? 'OK' : 'ERROR',
            json_encode($gateway->getResponseData()),
            'recurring-payment-manual-charge',
            $payment->id
        );

        if (!$gateway->isSuccessful()) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_FAIL);
            return false;
        }

        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);

        // Refresh model to load subscription details
        $payment = $this->paymentsRepository->find($payment->id);

        $retries = explode(', ', $this->applicationConfig->get('recurrent_payment_charges'));
        $retries = count($retries);
        $this->recurrentPaymentsRepository->add(
            $cid,
            $payment,
            $this->recurrentPaymentsRepository->calculateChargeAt($payment),
            null,
            --$retries
        );

        return true;
    }
}
