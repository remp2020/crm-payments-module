<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\GatewayFail;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Utils\Json;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Omnipay;
use Omnipay\PayPal\Message\ExpressCompletePurchaseResponse;
use Omnipay\PayPal\PayPalItem;
use Omnipay\PayPal\PayPalItemBag;
use Omnipay\PayPal\Support\InstantUpdateApi\BillingAgreement;
use Omnipay\PayPalReference\ExpressGateway;
use Tracy\Debugger;
use Tracy\ILogger;

class PaypalReference extends GatewayAbstract implements RecurrentPaymentInterface
{
    public const GATEWAY_CODE = 'paypal_reference';

    protected ExpressGateway $gateway;

    private $paymentMetaRepository;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        PaymentMetaRepository $paymentMetaRepository,
        Translator $translator
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function initialize()
    {
        /** @var ExpressGateway $gateway */
        $gateway = Omnipay::create('PayPalReference_Express');
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
        foreach ($payment->related('payment_items') as $item) {
            $items[] = $item->name;

            $ppItem = new PayPalItem();
            $ppItem->setName($item->name);
            $ppItem->setQuantity($item->count);
            $ppItem->setPrice($item->amount);
            $bag->add($ppItem);
        }
        $description = implode(", ", $items) . " ({$this->translator->translate('payments.gateway.recurrent')})";

        $this->response = $this->gateway->purchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'description' => $description,
            'transactionId' => $payment->variable_symbol,
            'billingAgreement' => new BillingAgreement(true, $description),
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

        /** @var ExpressCompletePurchaseResponse $paymentResponse */
        $paymentResponse = $this->gateway->completePurchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionId' => $payment->variable_symbol
        ])->send();

        if ($paymentResponse->isSuccessful()) {
            $responseData = $paymentResponse->getData();
            $this->paymentMetaRepository->add($payment, 'transaction_id', $responseData['PAYMENTINFO_0_TRANSACTIONID']);

            $this->response = $this->gateway->createBillingAgreement([
                'token' => $paymentResponse->getData()['TOKEN'],
            ])->send();
        }

        return $paymentResponse->isSuccessful();
    }

    public function checkValid($token)
    {
        throw new InvalidRequestException("paypal reference gateway doesn't support checking if token is still valid");
    }

    public function checkExpire($recurrentPayments)
    {
        throw new InvalidRequestException("paypal reference gateway doesn't support token expiration checking (it should never expire)");
    }

    public function charge($payment, $token): string
    {
        $this->initialize();

        try {
            $this->response = $this->gateway->doReferenceTransaction([
                'referenceId' => $token,
                'amount' => $payment->amount,
                'currency' => $this->applicationConfig->get('currency'),
            ])->send();
        } catch (\Exception $exception) {
            $log = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ];
            Debugger::log(Json::encode($log));
            throw new GatewayFail($exception->getMessage(), $exception->getCode());
        }

        $this->checkChargeStatus($payment, $this->getResultCode());
        if ($this->response->isSuccessful()) {
            $responseData = $this->response->getData();
            $this->paymentMetaRepository->add($payment, 'transaction_id', $responseData['TRANSACTIONID']);
        }

        return self::CHARGE_OK;
    }

    public function hasRecurrentToken(): bool
    {
        $data = $this->getResponseData();
        return isset($data['BILLINGAGREEMENTID']);
    }

    public function getRecurrentToken()
    {
        $data = $this->getResponseData();
        return $data['BILLINGAGREEMENTID'];
    }

    public function getResultCode(): ?string
    {
        $data = $this->getResponseData();
        if (!isset($data['ACK'])) {
            Debugger::log("Paypal response data doesn't include ACK flag: " . Json::encode($data), ILogger::WARNING);
            return null;
        }
        return $data['ACK'];
    }

    public function getResultMessage(): ?string
    {
        $data = $this->getResponseData();
        if (!isset($data['ACK'])) {
            Debugger::log("Paypal response data doesn't include ACK flag: " . Json::encode($data), ILogger::WARNING);
            return null;
        }
        return $data['ACK'];
    }
}
