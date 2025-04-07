<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;

interface AssignRenewalPaymentToSubscriptionFormDataProviderInterface extends DataProviderInterface
{
    public function provide(array $params): Form;

    public function formSucceeded($form, $values);
}
