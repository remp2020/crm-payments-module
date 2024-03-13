<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Application\UI\Form;
use Nette\Utils\ArrayHash;

interface PaymentRefundFormDataProviderInterface extends DataProviderInterface
{
    const PATH = 'admin.dataprovider.payment_refund';

    public function provide(array $params): Form;

    public function formSucceeded(Form $form, ArrayHash $values): array;
}
