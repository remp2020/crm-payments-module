<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\ITranslator;
use Omnipay\Omnipay;
use Omnipay\PayPal\ExpressGateway;

class Paypal extends GatewayAbstract implements PaymentInterface
{
    /** @var ExpressGateway */
    protected $gateway;

    private $paymentMetaRepository;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        PaymentMetaRepository $paymentMetaRepository,
        ITranslator $translator
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function initialize()
    {
        $this->gateway = Omnipay::create('PayPal_Express');

        $this->gateway->setUsername($this->applicationConfig->get('paypal_username'));
        $this->gateway->setPassword($this->applicationConfig->get('paypal_password'));
        $this->gateway->setSignature($this->applicationConfig->get('paypal_signature'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('paypal_mode') == 'live'));
    }

    public function begin($payment)
    {
        $this->initialize();

        $this->response = $this->gateway->purchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionId' => $payment->variable_symbol,
            'returnUrl' => $this->generateReturnUrl($payment) . '?paypal_success=1&VS=' . $payment->variable_symbol,
            'cancelUrl' => $this->generateReturnUrl($payment) . '?paypal_success=0&VS=' . $payment->variable_symbol,
            'landingPage' => 'Login',
        ])->send();
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $this->response = $this->gateway->completePurchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionId' => $payment->variable_symbol
        ])->send();

        if ($this->response->isSuccessful()) {
            $responseData = $this->response->getData();
            $this->paymentMetaRepository->add($payment, 'transaction_id', $responseData['PAYMENTINFO_0_TRANSACTIONID']);
        }

        return $this->response->isSuccessful();
    }
}
