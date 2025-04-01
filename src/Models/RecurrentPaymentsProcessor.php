<?php

namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Events\BeforeRecurrentPaymentChargeEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentFailEvent;
use Crm\PaymentsModule\Events\RecurrentPaymentFailTryEvent;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tracy\Debugger;

class RecurrentPaymentsProcessor
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly PaymentLogsRepository $paymentLogsRepository,
        private readonly ApplicationConfig $applicationConfig,
        private readonly Emitter $emitter,
    ) {
    }

    public function processChargedRecurrent(
        $recurrentPayment,
        $paymentStatus,
        $resultCode,
        $resultMessage,
        $customChargeAmount = null,
        \DateTime $chargeAt = null
    ) {
        $this->paymentsRepository->updateStatus($recurrentPayment->payment, $paymentStatus, true);
        $payment = $this->paymentsRepository->find($recurrentPayment->payment->id); // refresh to get fresh object

        $this->recurrentPaymentsRepository->createFromPayment(
            $payment,
            $recurrentPayment->payment_method->external_token,
            $chargeAt,
            $customChargeAmount
        );

        $this->recurrentPaymentsRepository->setCharged($recurrentPayment, $payment, $resultCode, $resultMessage);
    }

    public function processPendingRecurrent(ActiveRow $recurrentPayment)
    {
        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'state' => RecurrentPaymentsRepository::STATE_PENDING,
        ]);
    }

    public function processFailedRecurrent(
        ActiveRow $recurrentPayment,
        $resultCode,
        ?string $resultMessage,
        ?float $customChargeAmount = null
    ) {
        // stop recurrent if there are no more retries available
        if ($recurrentPayment->retries === 0) {
            $this->processStoppedRecurrent(
                $recurrentPayment,
                $resultCode,
                $resultMessage
            );
            return;
        }

        $this->paymentsRepository->updateStatus($recurrentPayment->payment, PaymentStatusEnum::Fail->value);
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
            $recurrentPayment->payment_method,
            $payment,
            $nextCharge,
            $customChargeAmount,
            $recurrentPayment->retries - 1,
        );

        if (isset($resultMessage) && strlen($resultMessage) > 250) {
            Debugger::log('Result message of failed recurrent is too long: [' . $resultMessage . '].', Debugger::ERROR);
            $resultMessage = substr($resultMessage, 0, 250);
        }

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
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Fail->value);

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
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Fail->value);

        $this->recurrentPaymentsRepository->add(
            $recurrentPayment->payment_method,
            $payment,
            $nextCharge,
            $customChargeAmount,
            $recurrentPayment->retries,
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
        if (!$gateway instanceof GatewayAbstract) {
            throw new \Exception('To user chargeRecurrentUsingCid you must provide implementation of GatewayAbstract: ' . get_class($gateway));
        }

        $this->emitter->emit(new BeforeRecurrentPaymentChargeEvent($payment, $cid)); // ability to modify payment
        $payment = $this->paymentsRepository->find($payment->id); // reload

        try {
            $gateway->charge($payment, $cid);
        } catch (\Exception $e) {
            $this->paymentsRepository->updateStatus(
                $payment,
                PaymentStatusEnum::Fail->value,
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
            $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Fail->value);
            return false;
        }

        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value, true);

        // Refresh model to load subscription details
        $payment = $this->paymentsRepository->find($payment->id);
        $this->recurrentPaymentsRepository->createFromPayment($payment, $cid);

        return true;
    }
}
