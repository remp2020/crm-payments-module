<?php

namespace Crm\PaymentsModule\Models\Gateways;

class Free extends GatewayAbstract
{
    public const GATEWAY_CODE = 'free';

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
