<?php

namespace Crm\PaymentsModule\Models\Wallet;

class PayloadSerializer
{
    public function serialize(TransactionPayload $payload): string
    {
        $data = [
            'merchantId' => $payload->getMerchantId(),
            'amount' => $payload->getAmount(),
            'currency' => $payload->getCurrency(),
            'clientIpAddress' => $payload->getClientIpAddress(),
            'clientName' => $payload->getClientName(),
            'timestamp' => $payload->getTimestamp(),
        ];

        // one of the two has to be set
        if ($payload->getVariableSymbol()) {
            $data['variableSymbol'] = $payload->getVariableSymbol();
        }
        if ($payload->getE2eReference()) {
            $data['e2eReference'] = $payload->getE2eReference();
        }

        // one of the two has to be set
        if ($payload->getGooglePayToken()) {
            $data['googlePayToken'] = $payload->getGooglePayToken();
        }
        if ($payload->getApplePayToken()) {
            $data['applePay'] = [
                'token' => $payload->getApplePayToken(),
            ];
        }

        if ($payload->isPreAuthorization()) {
            $data['preAuthorization'] = $payload->isPreAuthorization();
        }

        if ($payload->getTdsTermUrl()) {
            $data['tdsTermUrl'] = $payload->getTdsTermUrl();
        }

        if ($payload->getTdsData() && $payload->getTdsData()->isEntered()) {
            $data['tdsData'] = $payload->getTdsData()->getData();
        }

        if ($payload->getIpsData() && $payload->getIpsData()->isEntered()) {
            $data['ipspData'] = $payload->getIpsData()->getData();
        }

        return json_encode($data);
    }
}
