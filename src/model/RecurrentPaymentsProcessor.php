<?php

namespace Crm\PaymentsModule;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\RecurrentPaymentFailEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentFailTryEvent;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Utils\DateTime;

class RecurrentPaymentsProcessor
{
    private $recurrentPaymentsRepository;

    private $paymentsRepository;

    private $paymentLogsRepository;

    private $applicationConfig;

    private $emitter;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentsRepository $paymentsRepository,
        PaymentLogsRepository $paymentLogsRepository,
        ApplicationConfig $applicationConfig,
        Emitter $emitter
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentLogsRepository = $paymentLogsRepository;
        $this->applicationConfig = $applicationConfig;
        $this->emitter = $emitter;
    }

    public function processChargedRecurrent(
        $recurrentPayment,
        $paymentStatus,
        $resultCode,
        $resultMessage,
        $customChargeAmount = null,
        \DateTime $chargeAt = null
    ) {
        $this->paymentsRepository->updateStatus($recurrentPayment->payment, $paymentStatus);
        $payment = $this->paymentsRepository->find($recurrentPayment->payment->id); // refresh to get fresh object

        $this->recurrentPaymentsRepository->createFromPayment(
            $payment,
            $recurrentPayment->cid,
            $chargeAt,
            $customChargeAmount
        );

        $this->recurrentPaymentsRepository->setCharged($recurrentPayment, $payment, $resultCode, $resultMessage);
    }

    public function processPendingRecurrent(IRow $recurrentPayment)
    {
        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'state' => RecurrentPaymentsRepository::STATE_PENDING,
        ]);
    }

    public function processFailedRecurrent($recurrentPayment, $resultCode, $resultMessage, $customChargeAmount = null)
    {
        // stop recurrent if there are no more retries available
        if ($recurrentPayment->retries === 0) {
            $this->processStoppedRecurrent(
                $recurrentPayment,
                $resultCode,
                $resultMessage
            );
            return;
        }

        $this->paymentsRepository->updateStatus($recurrentPayment->payment, PaymentsRepository::STATUS_FAIL);
        $payment = $this->paymentsRepository->find($recurrentPayment->payment_id); // refresh to get fresh object

        $charges = explode(', ', $this->applicationConfig->get('recurrent_payment_charges'));
        $charges = array_reverse((array)$charges);
        $next = new \DateInterval(end($charges));
        if (isset($charges[$recurrentPayment->retries])) {
            $next = new \DateInterval($charges[$recurrentPayment->retries]);
        }
        $nextCharge = new DateTime();
        $nextCharge->add($next);

        $this->recurrentPaymentsRepository->add(
            $recurrentPayment->cid,
            $payment,
            $nextCharge,
            $customChargeAmount,
            $recurrentPayment->retries - 1
        );

        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'state' => RecurrentPaymentsRepository::STATE_CHARGE_FAILED,
            'status' => $resultCode,
            'approval' => $resultMessage,
        ]);

        $this->emitter->emit(new RecurrentPaymentFailTryEvent($recurrentPayment));
    }

    public function processStoppedRecurrent($recurrentPayment, $resultCode, $resultMessage)
    {
        $payment = $this->paymentsRepository->find($recurrentPayment->payment_id);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_FAIL);

        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'state' => RecurrentPaymentsRepository::STATE_SYSTEM_STOP,
            'status' => $resultCode,
            'approval' => $resultMessage,
        ]);

        $this->emitter->emit(new RecurrentPaymentFailEvent($recurrentPayment));
    }

    public function processRecurrentChargeError($recurrentPayment, $resultCode, $resultMessage, $customChargeAmount = null)
    {
        $next = new \DateInterval($this->applicationConfig->get('recurrent_payment_gateway_fail_delay'));
        $nextCharge = new DateTime();
        $nextCharge->add($next);

        $payment = $this->paymentsRepository->find($recurrentPayment->payment_id);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_FAIL);

        $this->recurrentPaymentsRepository->add(
            $recurrentPayment->cid,
            $payment,
            $nextCharge,
            $customChargeAmount,
            $recurrentPayment->retries
        );

        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'state' => RecurrentPaymentsRepository::STATE_CHARGE_FAILED,
            'status' => $resultCode,
            'approval' => $resultMessage,
        ]);

        $this->emitter->emit(new RecurrentPaymentFailTryEvent($recurrentPayment));
    }

    public function chargeRecurrentUsingCid(ActiveRow $payment, string $cid, RecurrentPaymentInterface $gateway): bool
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

        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID, true);

        // Refresh model to load subscription details
        $payment = $this->paymentsRepository->find($payment->id);
        $this->recurrentPaymentsRepository->createFromPayment($payment, $cid);

        return true;
    }
}
