<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Database\Table\IRow;

interface CanUpdatePaymentItemDataProviderInterface extends DataProviderInterface
{
    /**
     * @param array $params {
     *   @type IRow $paymentItem
     * }
     */
    public function provide(array $params);
}
