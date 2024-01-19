<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface CanUpdatePaymentItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{paymentItem: ActiveRow} $params
     */
    public function provide(array $params);
}
