<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\RecurrentPaymentFailStop;
use Crm\PaymentsModule\RecurrentPaymentFailTry;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\ITranslator;
use Nette\Utils\Strings;

abstract class GatewayAbstract
{
    protected $cancelErrorCodes = [];

    /** @var \Omnipay\Common\Message\AbstractResponse */
    protected $response;

    protected $linkGenerator;

    protected $applicationConfig;

    protected $httpResponse;

    protected $translator;

    abstract protected function initialize();

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        ITranslator $translator
    ) {
        $this->linkGenerator = $linkGenerator;
        $this->applicationConfig = $applicationConfig;
        $this->httpResponse = $httpResponse;
        $this->translator = $translator;
    }

    public function isSuccessful()
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
            } else {
                return $this->response->getRedirectUrl();
            }
        } else {
            throw new CannotProcessPayment($this->response->getMessage());
        }
    }

    protected function generateReturnUrl($payment)
    {
        $paymentGateway = $payment->payment_gateway;
        $code = str_replace('_', '', $paymentGateway->code);
        $code = Strings::firstUpper($code);

        return $this->linkGenerator->link('SalesFunnel:SalesFunnel:ReturnPayment' . $code);
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

        return $recurrentPayment->retries == 0 || in_array($resultCode, $this->cancelErrorCodes);
    }

    protected function handleSuccessful($response)
    {
        // nothing to do here
    }
}
