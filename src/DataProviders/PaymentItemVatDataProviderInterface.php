<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;

interface PaymentItemVatDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.payment_item_vat';

    public function getVat(PaymentItemInterface $paymentItem): ?float;
}
