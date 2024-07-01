<?php

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\SalesFunnelModule\DataProviders\SalesFunnelVariablesDataProviderInterface;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Security\User;
use Tracy\Debugger;

final class SalesFunnelTwigVariablesDataProvider implements SalesFunnelVariablesDataProviderInterface
{
    public function __construct(
        private VatRatesRepository $vatRatesRepository,
        private CountriesRepository $countriesRepository,
        private User $user,
        private OneStopShop $oneStopShop,
        private UsersRepository $usersRepository,
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

        $enabled = $this->oneStopShop->isEnabled();
        $oneStopShopData = [
            'enabled' => $enabled,
        ];

        if ($enabled) {
            $user = $this->user->isLoggedIn() ? $this->usersRepository->find($this->user->getId()) : null;
            try {
                // Prefilled payment country based on user and request (e.g. IP) data
                $countryResolution = $this->oneStopShop->resolveCountry($user);
                if ($countryResolution) {
                    $oneStopShopData['prefilledCountryCode'] = $countryResolution->countryCode;
                    $oneStopShopData['prefilledCountryName'] = $vatRates[$countryResolution->countryCode]['country_name'] ?? null;
                    $oneStopShopData['prefilledCountryReason'] = $countryResolution->getReasonValue();
                } else {
                    $oneStopShopData['prefilledCountryCode'] = null;
                }
            } catch (OneStopShopCountryConflictException|GeoIpException $e) {
                Debugger::log($e);
                $oneStopShopData['prefilledCountryCode'] = null;
            }
        }

        $params['oneStopShop'] = $oneStopShopData;
        return $params;
    }
}
