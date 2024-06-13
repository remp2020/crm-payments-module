<?php

namespace Crm\PaymentsModule\Tests\Gateways;

use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Omnipay\Common\Exception\InvalidRequestException;

/**
 * Test serves as a dummy implementation of gateway to be used in tests. In the future it will be possible to configure
 * the gateway before its use to differentiate responses based on the arbitrary test rules.
 */
class TestRecurrentGateway extends GatewayAbstract implements RecurrentPaymentInterface
{
    public const GATEWAY_CODE = 'recurrent';

    protected function initialize()
    {
    }

    public function begin($payment)
    {
    }

    public function complete($payment): ?bool
    {
        return true;
    }

    public function charge($payment, $token): string
    {
        return self::CHARGE_OK;
    }

    public function isSuccessful(): bool
    {
        return true;
    }

    public function checkValid($token)
    {
        throw new InvalidRequestException("test recurrent gateway doesn't support token validity checking");
    }

    public function checkExpire($recurrentPayments)
    {
        throw new InvalidRequestException("test recurrent gateway doesn't support token expiration checking");
    }

    public function hasRecurrentToken(): bool
    {
        return true;
    }

    public function getRecurrentToken()
    {
        return 'test_recurrent_token';
    }

    public function getResultCode(): ?string
    {
        return 'test_result_code';
    }

    public function getResultMessage(): ?string
    {
        return 'test_result_message';
    }
}
