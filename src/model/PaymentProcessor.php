<?php

namespace Crm\PaymentsModule;

use Crm\PaymentsModule\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Http\Request;
use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class PaymentProcessor
{
    /** @var GatewayFactory */
    private $gatewayFactory;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var PaymentLogsRepository */
    private $paymentLogsRepository;

    /** @var Request */
    protected $request;

    public function __construct(
        GatewayFactory $gatewayFactory,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        PaymentLogsRepository $paymentLogsRepository,
        Request $request
    ) {
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->paymentLogsRepository = $paymentLogsRepository;
        $this->request = $request;
    }

    public function begin($payment, $allowRedirect = true)
    {
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
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

            if ((boolean)$payment->payment_gateway->is_recurrent) {
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
