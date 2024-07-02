<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;

interface OneStopShopVatRateDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.one_stop_shop_vat_rate';

    public function provide(array $params): ?float;
}
