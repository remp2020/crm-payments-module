<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Utils\Strings;
use Omnipay\Omnipay;
use Omnipay\TatraPay\Gateway;

class Tatrapay extends GatewayAbstract
{
    public const GATEWAY_CODE = 'tatrapay';

    protected Gateway $gateway;

    protected function initialize()
    {
        /** @var Gateway $gateway */
        $gateway = Omnipay::create('TatraPay');
        $this->gateway = $gateway;

        $this->gateway->setMid($this->applicationConfig->get('tatrapay_mid'));
        $this->gateway->setSharedSecret($this->applicationConfig->get('tatrapay_sharedsecret'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('tatrapay_mode') == 'live'));
        $this->gateway->setTestHost($this->testHost);
    }

    public function begin($payment)
    {
        $this->initialize();

        $name = null;
        if (!empty($payment->ref('user')->last_name)) {
            $name = Strings::webalize($payment->user->last_name . ' ' . $payment->user->first_name);
        }
        if (!$name) {
            $name = Strings::webalize($payment->user->public_name);
        }

        $name = substr($name, 0, 30);

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
            'rurl' => $this->generateReturnUrl($payment),
            'aredir' => true,
            'name' => $name,
            'lang' => \Locale::getPrimaryLanguage($payment->user->locale),
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
