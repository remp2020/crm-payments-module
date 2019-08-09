<?php

namespace Crm\PaymentsModule\Gateways;

class BankTransfer extends GatewayAbstract
{
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
        $url = $this->linkGenerator->link(
            'Payments:Return:bankTransfer',
            [
                'VS' => $payment->variable_symbol,
            ]
        );
        $this->httpResponse->redirect($url);
        exit();
    }

    public function complete($payment): ?bool
    {
        return true;
    }
}
