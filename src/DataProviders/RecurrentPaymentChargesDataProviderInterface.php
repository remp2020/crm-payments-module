<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Nette\Database\Table\ActiveRow;

interface RecurrentPaymentChargesDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.recurrent_payment_charges';

    /**
     * Provides list of retry intervals (`recurrent_payment_charges`) to be used for given recurrent payment.
     *
     * @param ActiveRow $recurrentPayment
     *
     * @return string[]|null List of DateInterval specifications (e.g. `['PT15M', 'PT6H', 'P1D']`) ordered the same
     *                       way as the `recurrent_payment_charges` configuration option, or null when the default
     *                       configuration should be used.
     */
    public function provide(ActiveRow $recurrentPayment): ?array;
}
