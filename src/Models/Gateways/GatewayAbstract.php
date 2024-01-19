<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\CannotProcessPayment;
use Crm\PaymentsModule\Models\RecurrentPaymentFailStop;
use Crm\PaymentsModule\Models\RecurrentPaymentFailTry;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\Translator;

abstract class GatewayAbstract implements PaymentInterface
{
    protected $cancelErrorCodes = [];

    protected ?string $testHost;

    protected $response;

    protected $linkGenerator;

    protected $applicationConfig;

    protected $httpResponse;

    protected $translator;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        Translator $translator
    ) {
        $this->linkGenerator = $linkGenerator;
        $this->applicationConfig = $applicationConfig;
        $this->httpResponse = $httpResponse;
        $this->translator = $translator;
    }

    public function isSuccessful(): bool
    {
        return (isset($this->response) && ($this->response->isSuccessful() || $this->response->isRedirect()));
    }

    public function isCancelled()
    {
        return (isset($this->response) && $this->response->isCancelled());
    }

    public function isNotSettled()
    {
        return false;
    }

    public function getResponseData()
    {
        return isset($this->response) ? $this->response->getData() : [];
    }

    public function process($allowRedirect = true)
    {
        if (!isset($this->response)) {
            throw new CannotProcessPayment();
        }

        if ($this->response->isSuccessful()) {
            $this->handleSuccessful($this->response);
        } elseif ($this->response->isRedirect()) {
            if ($allowRedirect) {
                $this->response->redirect();
                exit;
            }

            return new ProcessResponse('url', $this->response->getRedirectUrl());
        } else {
            throw new CannotProcessPayment($this->response->getMessage());
        }
    }

    public function setTestHost(?string $testHost)
    {
        $this->testHost = $testHost;
    }

    protected function generateReturnUrl($payment, $params = [])
    {
        return $this->linkGenerator->link(
            'Payments:Return:gateway',
            array_merge([
                'gatewayCode' => $payment->payment_gateway->code,
            ], $params)
        );
    }

    protected function checkChargeStatus($payment, $resultCode)
    {
        if (!$this->response->isSuccessful()) {
            if ($this->hasStopRecurrentPayment($payment, $resultCode)) {
                throw new RecurrentPaymentFailStop();
            }

            throw new RecurrentPaymentFailTry();
        }
    }

    protected function hasStopRecurrentPayment($payment, $resultCode)
    {
        $recurrentPayment = $payment->related('recurrent_payments.payment_id')->fetch();
        if (!$recurrentPayment) {
            // upgrade payment which is not linked with a recurrent yet
            return false;
        }

        return $recurrentPayment->retries == 0 || in_array($resultCode, $this->cancelErrorCodes, true);
    }

    protected function handleSuccessful($response)
    {
        // nothing to do here
    }
}
