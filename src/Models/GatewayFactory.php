<?php
namespace Crm\PaymentsModule\Models;

use Crm\PaymentsModule\Models\Gateways\PaymentInterface;
use Nette\DI\Container;

class GatewayFactory
{
    protected $container;

    private $gateways = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function registerGateway($code, $gatewayClass = null)
    {
        if (isset($this->gateways[$code])) {
            throw new \Exception('trying to register gateway with code that is already used: ' . $code);
        }
        $this->gateways[$code] = $gatewayClass;
    }

    /**
     * @param $code
     * @return PaymentInterface
     * @throws UnknownPaymentMethodCode
     */
    public function getGateway($code)
    {
        if (!isset($this->gateways[$code])) {
            throw new UnknownPaymentMethodCode("Payment code: {$code}");
        }
        $gateway = $this->container->getByType($this->gateways[$code]);
        if (!$gateway) {
            throw new \Exception("gateway doesn't have any registered implementation; add implementation or use only on backend: " . get_class($gateway));
        }
        if (!$gateway instanceof PaymentInterface) {
            throw new \Exception("accessed gateway doesn't implement PaymentInterface: " . get_class($gateway));
        }
        return $gateway;
    }

    public function getRegisteredCodes(): array
    {
        return array_keys($this->gateways);
    }
}
