<?php

namespace Crm\PaymentsModule\Hermes;

use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\CancellableTokenInterface;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class AnonymizedExternalTokenHandler implements HandlerInterface
{
    public function __construct(
        private readonly PaymentMethodsRepository $paymentMethodsRepository,
        private readonly GatewayFactory $gatewayFactory,
    ) {
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();

        $externalToken = $payload['external_token'];
        $paymentMethod = $this->paymentMethodsRepository->find($payload['payment_method_id']);

        $gateway = $this->gatewayFactory->getGateway($paymentMethod->payment_gateway->code);
        if ($gateway instanceof CancellableTokenInterface) {
            $gateway->cancelToken($externalToken);
        }

        return true;
    }
}
