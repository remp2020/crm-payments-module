<?php

namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Events\BeforePaymentBeginEvent;
use Crm\PaymentsModule\Models\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
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
    private GatewayFactory $gatewayFactory;

    private PaymentsRepository $paymentsRepository;

    private RecurrentPaymentsRepository $recurrentPaymentsRepository;

    private PaymentLogsRepository $paymentLogsRepository;

    protected Request $request;

    private Emitter $emitter;

    public function __construct(
        GatewayFactory $gatewayFactory,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentLogsRepository $paymentLogsRepository,
        Request $request,
        Emitter $emitter
    ) {
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentLogsRepository = $paymentLogsRepository;
        $this->request = $request;
        $this->emitter = $emitter;
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

    public function complete($payment, $callback)
    {
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof GatewayAbstract) {
            throw new \Exception('To use PaymentProcessor, the gateway must be implementation of GatewayAbstract: ' . get_class($gateway));
        }

        if ($payment->status == PaymentsRepository::STATUS_PAID) {
            $callback($payment, $gateway);
            return;
        }

        if ($payment->status == PaymentsRepository::STATUS_PREPAID) {
            $this->paymentLogsRepository->add(
                'OK',
                Json::encode([]),
                $this->request->getUrl(),
                $payment->id
            );
            $callback($payment, $gateway);
            return;
        }

        $result = $gateway->complete($payment);
        if ($result === true) {
            $status = PaymentsRepository::STATUS_PAID;
            if ($gateway instanceof AuthorizationInterface) {
                $status = PaymentsRepository::STATUS_AUTHORIZED;
            }
            $this->paymentsRepository->updateStatus($payment, $status, true);
            $payment = $this->paymentsRepository->find($payment->id);

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
        } elseif ($result === false) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_FAIL);
            $payment = $this->paymentsRepository->find($payment->id);
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

        $callback($payment, $gateway);
    }
}
