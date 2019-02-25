<?php

namespace Crm\PaymentsModule\DataProvider;

use Crm\ApplicationModule\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface PaymentFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;
}
