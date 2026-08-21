<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\ApplicationModule\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

interface PaymentGatewayFormDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'admin.dataprovider.payment_gateway_form';

    public function provide(Form $form, ActiveRow $paymentGateway): Form;

    /**
     * @return array{Form, ArrayHash}
     */
    public function formSucceeded(Form $form, ArrayHash $values): array;
}
