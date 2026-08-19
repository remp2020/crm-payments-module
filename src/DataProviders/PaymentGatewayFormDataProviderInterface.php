<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Utils\ArrayHash;

interface PaymentGatewayFormDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'admin.dataprovider.payment_gateway_form';

    /**
     * @param array{form: Form, paymentGateway: \Nette\Database\Table\ActiveRow} $params
     */
    public function provide(array $params): Form;

    /**
     * @return array{Form, ArrayHash}
     */
    public function formSucceeded(Form $form, ArrayHash $values): array;
}
