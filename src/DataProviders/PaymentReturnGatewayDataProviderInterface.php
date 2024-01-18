<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface PaymentReturnGatewayDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): string;
}
