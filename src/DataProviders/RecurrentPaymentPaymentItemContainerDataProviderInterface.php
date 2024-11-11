<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Nette\Database\Table\ActiveRow;

interface RecurrentPaymentPaymentItemContainerDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.recurrent_payment_payment_item_container';

    /**
     * @param ActiveRow $recurrentPayment
     * @param ActiveRow $subscriptionType
     *
     * @return PaymentItemContainer|null Return container when overriding default implementation of recurrent payment
     *                                   container creation or null, when default implementation should be used.
     */
    public function createPaymentItemContainer(
        ActiveRow $recurrentPayment,
        ActiveRow $subscriptionType,
    ): ?PaymentItemContainer;
}
