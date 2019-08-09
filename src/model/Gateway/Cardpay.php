<?php

namespace Crm\PaymentsModule\Gateways;

use Nette\Utils\Strings;
use Omnipay\CardPay\Gateway;
use Omnipay\Omnipay;

class Cardpay extends GatewayAbstract
{
    /** @var Gateway */
    protected $gateway;

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

        $name = preg_replace('/\+/', '_', $payment->user->email);
        if (!empty($payment->ref('user')->last_name)) {
            $name = Strings::webalize($payment->user->last_name . ' ' . $payment->user->first_name);
        }

        $name = substr($name, 0, 30);

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
            'rurl' => $this->generateReturnUrl($payment),
            'aredir' => true,
            'name' =>  $name,
        ];

        $referenceEmail = $this->applicationConfig->get('comfortpay_rem');
        if ($referenceEmail) {
            $request['rem'] = $referenceEmail;
        }

        $this->response = $this->gateway->purchase($request)->send();
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
        ];

        $this->response = $this->gateway->completePurchase($request)->send();

        return $this->response->isSuccessful();
    }

    public function isNotSettled()
    {
        return $this->getResponseData()['RES'] === "TOUT";
    }
}
