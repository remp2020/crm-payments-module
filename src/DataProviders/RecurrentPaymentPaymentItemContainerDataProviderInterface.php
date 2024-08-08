<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;

interface RecurrentPaymentPaymentItemContainerDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): ?PaymentItemContainer;
}
