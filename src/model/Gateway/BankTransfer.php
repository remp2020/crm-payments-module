<?php

namespace Crm\PaymentsModule\Gateways;

class BankTransfer extends GatewayAbstract
{
    const GATEWAY_CODE = 'bank_transfer';

    public function isSuccessful(): bool
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
        $url = $this->generateReturnUrl($payment, [
            'VS' => $payment->variable_symbol,
        ]);
        $this->httpResponse->redirect($url);
        exit();
    }

    public function complete($payment): ?bool
    {
        return null;
    }
}
