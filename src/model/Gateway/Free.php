<?php

namespace Crm\PaymentsModule\Gateways;

class Free extends GatewayAbstract
{

    protected function initialize()
    {
    }

    public function begin($payment)
    {
        $url = $this->generateReturnUrl($payment, [
            'vs' => $payment->variable_symbol
        ]);
        $this->httpResponse->redirect($url);
        exit();
    }

    public function complete($payment): ?bool
    {
        return true;
    }
}
