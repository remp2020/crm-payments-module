<?php

namespace Crm\PaymentsModule\Gateways;

class BankTransfer extends GatewayAbstract
{
    public function isSuccessful()
    {
        return true;
    }

    public function process($allowRedirect = true)
    {
    }

    protected function initialize()
    {
    }

    public function begin($payment)
    {
        $url = $this->generateReturnUrl($payment) . '?VS=' . $payment->variable_symbol;
        $this->httpResponse->redirect($url);
        exit();
    }

    public function complete($payment): ?bool
    {
        return true;
    }
}
