<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelVariablesDataProviderInterface;
use Crm\UsersModule\Repositories\CountriesRepository;

final class SalesFunnelTwigVariablesDataProvider implements SalesFunnelVariablesDataProviderInterface
{
    public function __construct(
        private VatRatesRepository $vatRatesRepository,
        private CountriesRepository $countriesRepository,
        private OneStopShop $oneStopShop,
    ) {
    }

    public function provide(array $params): array
    {
        if (!isset($params[self::PARAM_SALES_FUNNEL])) {
            throw new DataProviderException('missing [' . self::PARAM_SALES_FUNNEL . '] within data provider params');
        }

        return $this->oneStopShop();
    }

    private function oneStopShop(): array
    {
        $vatRates = [];
        foreach ($this->vatRatesRepository->getVatRates() as $row) {
            $vatRates[$row->country->iso_code] = [
                'country_name' => $row->country->name,
                ...$row->toArray(),
            ];
        }
        $params = [];
        $params['countryVatRates'] = $vatRates;
        $params['countries'] = $this->countriesRepository->all()->fetchPairs('iso_code', 'name');
        $params['oneStopShop'] = $this->oneStopShop->getFrontendData();
        return $params;
    }
}
