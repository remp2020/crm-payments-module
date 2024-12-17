<?php

namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Events\BeforePaymentBeginEvent;
use Crm\PaymentsModule\Models\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Gateways\RecurrentAuthorizationInterface;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use League\Event\Emitter;
use Nette\Http\Request;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class PaymentProcessor
{
    public function __construct(
        private GatewayFactory $gatewayFactory,
        private PaymentsRepository $paymentsRepository,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private PaymentLogsRepository $paymentLogsRepository,
        protected Request $request,
        private Emitter $emitter
    ) {
    }

    public function begin($payment, $allowRedirect = true)
    {
        $this->emitter->emit(new BeforePaymentBeginEvent($payment)); // ability to modify payment
        $payment = $this->paymentsRepository->find($payment->id); // payment could have been altered, reload

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof GatewayAbstract) {
            throw new \Exception('To use PaymentProcessor, the gateway must be implementation of GatewayAbstract: ' . get_class($gateway));
        }

        $gateway->begin($payment);

        $this->paymentLogsRepository->add(
            $gateway->isSuccessful() ? 'OK' : 'ERROR',
            Json::encode($gateway->getResponseData()),
            $this->request->getUrl(),
            $payment->id
        );

        return $gateway->process($allowRedirect);
    }

    /**
     * @param      $payment
     * @param      $callback
     * @param bool $preventPaymentStatusUpdate if true, this also means no recurrent payment will be created
     *
     * @return void
     * @throws UnknownPaymentMethodCode
     * @throws \Nette\Utils\JsonException
     */
    public function complete($payment, $callback, bool $preventPaymentStatusUpdate = false)
    {
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof GatewayAbstract) {
            throw new \Exception('To use PaymentProcessor, the gateway must be implementation of GatewayAbstract: ' . get_class($gateway));
        }

        if ($payment->status === PaymentsRepository::STATUS_PAID) {
            $callback($payment, $gateway, PaymentsRepository::STATUS_PAID);
            return;
        }

        if ($payment->status === PaymentsRepository::STATUS_PREPAID) {
            $this->paymentLogsRepository->add(
                'OK',
                Json::encode([]),
                $this->request->getUrl(),
                $payment->id
            );
            $callback($payment, $gateway, PaymentsRepository::STATUS_PREPAID);
            return;
        }

        $status = $payment->status;
        $result = $gateway->complete($payment);
        if ($result === true) {
            $status = PaymentsRepository::STATUS_PAID;
            if ($gateway instanceof AuthorizationInterface || $gateway instanceof RecurrentAuthorizationInterface) {
                $status = PaymentsRepository::STATUS_AUTHORIZED;
            }

            if (!$preventPaymentStatusUpdate) {
                $this->paymentsRepository->updateStatus($payment, $status, true);
                $payment = $this->paymentsRepository->find($payment->id);
                $this->createRecurrentPayment($payment, $gateway);
            }
        } elseif ($result === false) {
            $status = PaymentsRepository::STATUS_FAIL;
            if (!$preventPaymentStatusUpdate) {
                $this->paymentsRepository->updateStatus($payment, $status);
            }
        } elseif ($result === null) {
            // no action intentionally, not even log
            return;
        }

        $this->paymentLogsRepository->add(
            $gateway->isSuccessful() ? 'OK' : 'ERROR',
            Json::encode($gateway->getResponseData()),
            $this->request->getUrl(),
            $payment->id
        );

        $payment = $this->paymentsRepository->find($payment->id); // reload payment
        $callback($payment, $gateway, $status);
    }

    public function createRecurrentPayment($payment, $gateway): void
    {
        if ((boolean) $payment->payment_gateway->is_recurrent) {
            if (!$gateway instanceof RecurrentPaymentInterface) {
                throw new \Exception("Gateway flagged with 'is_recurrent' flag needs to implement RecurrentPaymentInterface: " . get_class($gateway));
            }
            if ($gateway->hasRecurrentToken()) {
                $this->recurrentPaymentsRepository->createFromPayment(
                    $payment,
                    $gateway->getRecurrentToken()
                );
            } else {
                Debugger::log("Could not create recurrent payment from payment [{$payment->id}], missing recurrent token.", ILogger::ERROR);
            }
        }
    }
}
