<?php

namespace Crm\PaymentsModule\Models\RedirectResolver;

use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectResolver;
use Nette\Database\Table\ActiveRow;

class AuthorizationPaymentCompleteRedirectResolver implements PaymentCompleteRedirectResolver
{
    private $gatewayFactory;

    public function __construct(GatewayFactory $gatewayFactory)
    {
        $this->gatewayFactory = $gatewayFactory;
    }

    public function wantsToRedirect(?ActiveRow $payment, string $status): bool
    {
        if (!$payment) {
            return false;
        }

        $paymentGateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if ($paymentGateway instanceof AuthorizationInterface) {
            return true;
        }

        return false;
    }

    public function redirectArgs(?ActiveRow $payment, string $status): array
    {
        if (!$payment) {
            throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
        }

        $paymentGateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$paymentGateway instanceof AuthorizationInterface) {
            throw new \Exception('unhandled status when requesting redirectArgs (did you check wantsToRedirect first?): ' . $status);
        }

        return [
            ':Payments:Methods:complete',
            [
                'paymentId' => $payment->id,
            ]
        ];
    }
}
