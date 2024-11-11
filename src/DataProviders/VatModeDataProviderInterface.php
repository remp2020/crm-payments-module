<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\VatRate\VatMode;
use Nette\Database\Table\ActiveRow;

interface VatModeDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.vat_mode_data_provider';

    /**
     * @param ActiveRow $user
     *
     * @return VatMode|null returns either VatMode or null - leaving VatMode resolution to default implementation
     */
    public function getVatMode(
        ActiveRow $user,
    ): ?VatMode;
}
