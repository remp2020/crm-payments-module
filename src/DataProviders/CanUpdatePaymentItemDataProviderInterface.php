<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface CanUpdatePaymentItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array{paymentItem: ActiveRow} $params
     */
    public function provide(array $params);
}
