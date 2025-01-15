<?php

namespace Crm\PaymentsModule\Tests\Gateways;

use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Gateways\PaymentInterface;

/**
 * Test serves as a dummy implementation of gateway to be used in tests. In the future it will be possible to configure
 * the gateway before its use to differentiate responses based on the arbitrary test rules.
 */
class TestSingleGateway extends GatewayAbstract implements PaymentInterface
{
    public const GATEWAY_CODE = 'test_single';

    public function begin($payment)
    {
        throw new \Exception('Gateway is used only for test purposes');
    }

    public function complete($payment): ?bool
    {
        return true;
    }
}
