<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Localization\ITranslator;
use Nette\Utils\Strings;
use Omnipay\CardPay\Gateway;
use Omnipay\CardPay\Message\CancelAuthorizeRequest;
use Omnipay\Omnipay;
use Tracy\Debugger;

class CardPayAuthorization extends GatewayAbstract implements AuthorizationInterface
{
    public const GATEWAY_CODE = 'cardpay_authorization';

    /** @var Gateway */
    protected $gateway;

    protected $paymentMetaRepository;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        ITranslator $translator,
        PaymentMetaRepository $paymentMetaRepository
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);

        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function initialize()
    {
        $this->gateway = Omnipay::create('CardPay');

        $this->gateway->setMid($this->applicationConfig->get('cardpay_mid'));
        $this->gateway->setSharedSecret($this->applicationConfig->get('cardpay_sharedsecret'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('cardpay_mode') == 'live'));
    }

    public function begin($payment)
    {
        $this->initialize();

        $name = null;
        if (!empty($payment->ref('user')->last_name)) {
            $name = Strings::webalize("{$payment->user->last_name} {$payment->user->first_name}");
        }
        if (!$name) {
            $name = Strings::webalize($payment->user->public_name);
        }

        $name = substr($name, 0, 64);

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
            'rurl' => $this->generateReturnUrl($payment),
            'aredir' => true,
            'name' =>  $name,
            'tpay' => "Y", // Indicates yes for the ComfortPay registration
        ];

        $referenceEmail = $this->applicationConfig->get('comfortpay_rem');
        if ($referenceEmail) {
            $request['rem'] = $referenceEmail;
        }

        $this->response = $this->gateway->authorize($request)->send();
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
        ];

        $this->response = $this->gateway->completeAuthorize($request)->send();

        if ($this->response->isSuccessful()) {
            //TODO: Will be refactored/moved into separated table
            $this->paymentMetaRepository->add($payment, 'card_id', $this->response->getCid());
            $this->paymentMetaRepository->add($payment, 'card_number', $this->response->getCc());
            $this->paymentMetaRepository->add($payment, 'external_transaction_id', $this->response->getTransactionReference());
        }

        return $this->response->isSuccessful();
    }

    public function cancel(ActiveRow $payment): bool
    {
        $this->initialize();

        $externalTransactionId = $payment->related('payment_meta')
            ->where('key', 'external_transaction_id')
            ->fetchField('value');

        if (!$externalTransactionId) {
            Debugger::log("No `external_transaction_id` (TID) found for payment: {$payment->id}");
            return false;
        }

        $request = [
            'amount' => $payment->amount,
            'tid' => $externalTransactionId,
            'vs' => $payment->variable_symbol,
            'txn' => CancelAuthorizeRequest::TXN_STORNO_PREAUTHORIZATION,
        ];

        $this->response = $this->gateway->cancelAuthorize($request)->send();

        return $this->response->isSuccessful();
    }

    public function getAuthorizationAmount(): float
    {
        // Minimal value of TB authorization payment
        return 1.01;
    }
}
