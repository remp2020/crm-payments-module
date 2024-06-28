<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;

interface OneStopShopCountryResolutionDataProviderInterface extends DataProviderInterface
{
    public const PATH = 'payments.dataprovider.one_stop_shop_country_resolution';

    /**
     * @param array $params parameters passed to OneStopShop#resolveCountry
     *
     * @return CountryResolution|null
     * @throws OneStopShopCountryConflictException
     */
    public function provide(array $params): ?CountryResolution;
}
