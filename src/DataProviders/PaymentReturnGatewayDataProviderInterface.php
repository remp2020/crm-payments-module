<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;

interface PaymentReturnGatewayDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): string;
}
