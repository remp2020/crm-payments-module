<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Omnipay\Omnipay;
use Omnipay\PayPal\ExpressGateway;
use Omnipay\PayPal\PayPalItem;
use Omnipay\PayPal\PayPalItemBag;

class Paypal extends GatewayAbstract
{
    public const GATEWAY_CODE = 'paypal';

    protected ExpressGateway $gateway;

    private $paymentMetaRepository;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        PaymentMetaRepository $paymentMetaRepository,
        Translator $translator,
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function initialize()
    {
        /** @var ExpressGateway $gateway */
        $gateway = Omnipay::create('PayPal_Express');
        $this->gateway = $gateway;

        $this->gateway->setUsername($this->applicationConfig->get('paypal_username'));
        $this->gateway->setPassword($this->applicationConfig->get('paypal_password'));
        $this->gateway->setSignature($this->applicationConfig->get('paypal_signature'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('paypal_mode') == 'live'));
    }

    public function begin($payment)
    {
        $this->initialize();

        $bag = new PayPalItemBag();
        $items = [];
        foreach ($payment->related('payment_items') as $paymentItem) {
            $items[] = $paymentItem->name;

            $ppItem = new PayPalItem();
            $ppItem->setName($paymentItem->name);
            $ppItem->setQuantity($paymentItem->count);
            $ppItem->setPrice($paymentItem->amount);

            $bag->add($ppItem);
        }
        $description = implode(", ", $items) . " ({$this->translator->translate('payments.gateway.recurrent')})";

        $this->response = $this->gateway->purchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'description' => $description,
            'transactionId' => $payment->variable_symbol,
            'returnUrl' => $this->generateReturnUrl($payment, ['paypal_success' => '1', 'VS' => $payment->variable_symbol]),
            'cancelUrl' => $this->generateReturnUrl($payment, ['paypal_success' => '0', 'VS' => $payment->variable_symbol]),
            'landingPage' => 'Login',
            'localeCode' => $payment->user->locale,
            'items' => $bag,
        ])->send();
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $this->response = $this->gateway->completePurchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionId' => $payment->variable_symbol,
        ])->send();

        if ($this->response->isSuccessful()) {
            $responseData = $this->response->getData();
            $this->paymentMetaRepository->add($payment, 'transaction_id', $responseData['PAYMENTINFO_0_TRANSACTIONID']);
        }

        return $this->response->isSuccessful();
    }
}
