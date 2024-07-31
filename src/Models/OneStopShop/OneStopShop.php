<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\OneStopShop;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Request;
use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\DataProviders\OneStopShopVatRateDataProviderInterface;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\GeoIp\GeoIpInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Tracy\Debugger;

final class OneStopShop
{
    private array $frontendDataRequestCache = [];

    public function __construct(
        private readonly ApplicationConfig $applicationConfig,
        private readonly GeoIpInterface $geoIp,
        private readonly VatRatesRepository $vatRatesRepository,
        private readonly PaymentItemsRepository $paymentItemsRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly CountriesRepository $countriesRepository,
        private readonly User $user,
        private readonly UsersRepository $usersRepository,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->applicationConfig->get('one_stop_shop_enabled');
    }

    /**
     * @throws OneStopShopCountryConflictException
     */
    public function resolveCountry(
        ?ActiveRow $user = null,
        ?string $selectedCountryCode = null,
        ?ActiveRow $paymentAddress = null,
        ?PaymentItemContainer $paymentItemContainer = null,
        ?array $formParams = null,
        ?string $ipAddress = null,
        ?ActiveRow $previousPayment = null,
    ): ?CountryResolution {
        if (!$this->isEnabled()) {
            return null;
        }
        // Do not convert IP address to country here (before data providers),
        // since it may crash and may not be needed - a resolution with bigger priority can be returned instead.
        $ipAddress ??= $this->getIp();

        $dataProviderParams = compact(
            'user',
            'selectedCountryCode',
            'paymentAddress',
            'paymentItemContainer',
            'formParams',
            'ipAddress',
            'previousPayment'
        );

        /** @var OneStopShopCountryResolutionDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            OneStopShopCountryResolutionDataProviderInterface::PATH,
            OneStopShopCountryResolutionDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $countryResolution = $provider->provide($dataProviderParams);
            if ($countryResolution) {
                return $countryResolution;
            }
        }

        if ($paymentAddress && $paymentAddress->country) {
            if ($selectedCountryCode !== null && $selectedCountryCode !== $paymentAddress->country->iso_code) {
                throw new OneStopShopCountryConflictException("Conflicting selectedCountryCode [{$selectedCountryCode}] and paymentAddress country [{$paymentAddress->country->iso_code}]");
            }
            return new CountryResolution($paymentAddress->country, CountryResolutionTypeEnum::PaymentAddress);
        }

        if ($selectedCountryCode) {
            $selectedCountry = $this->countriesRepository->findByIsoCode($selectedCountryCode);
            if (!$selectedCountry) {
                throw new \RuntimeException("Invalid selected country code [{$selectedCountryCode}]");
            }
            return new CountryResolution($selectedCountry, CountryResolutionTypeEnum::UserSelected);
        }

        if ($previousPayment && $previousPayment->payment_country) {
            return new CountryResolution(
                $previousPayment->payment_country,
                CountryResolutionTypeEnum::PreviousPayment,
            );
        }

        try {
            $ipCountryCode = $this->geoIp->countryCode($ipAddress);
            // Some IP addresses do not have designated country (e.g. 199.64.72.254).
            if ($ipCountryCode) {
                $ipCountry = $this->countriesRepository->findByIsoCode($ipCountryCode);
                if (!$ipCountry) {
                    throw new \RuntimeException("Invalid IP country code [{$ipCountryCode}]");
                }
                return new CountryResolution($ipCountry, CountryResolutionTypeEnum::IpAddress);
            }
        } catch (GeoIpException $exception) {
            // Ignore
        }

        // If all other resolutions return no result and there is no conflict, default to default country
        return new CountryResolution($this->countriesRepository->defaultCountry(), CountryResolutionTypeEnum::DefaultCountry);
    }

    public function adjustPaymentVatRates(
        ActiveRow $payment,
        ?ActiveRow $paymentCountry
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$paymentCountry) {
            return;
        }

        // Do not adjust rates for default country
        if ($this->countriesRepository->defaultCountry()->iso_code === $paymentCountry->iso_code) {
            return;
        }

        foreach ($payment->related('payment_items') as $paymentItem) {
            $vatRate = $this->getCountryVatRate($paymentCountry, $paymentItem);
            $this->paymentItemsRepository->update($paymentItem, [
                'vat' => $vatRate,
                'amount_without_vat' =>  PaymentItemHelper::getPriceWithoutVAT($paymentItem->amount, $vatRate),
            ]);
        }
    }

    public function adjustPaymentItemContainerVatRates(
        PaymentItemContainer $paymentItemContainer,
        ?ActiveRow $paymentCountry
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$paymentCountry) {
            return;
        }

        // Do not adjust rates for default country
        if ($this->countriesRepository->defaultCountry()->iso_code === $paymentCountry->iso_code) {
            return;
        }

        foreach ($paymentItemContainer->items() as $paymentItem) {
            $vatRate = $this->getCountryVatRate($paymentCountry, $paymentItem);
            // Currently, VAT is stored as int in payment_items table
            $paymentItem->forceVat((int) $vatRate);
        }
    }

    public function getFrontendData(bool $enableRequestCache = true): OneStopShopFrontendData
    {
        $userId = $this->user->getId();

        if ($enableRequestCache && $userId && isset($this->frontendDataRequestCache[$userId])) {
            return $this->frontendDataRequestCache[$userId];
        }

        $user = $this->user->isLoggedIn() ? $this->usersRepository->find($this->user->getId()) : null;
        $enabled = $this->isEnabled();
        $countries = $this->countriesRepository->all()->fetchPairs('iso_code', 'name');
        $prefilledCountryCode = null;
        $prefilledCountryReason = null;
        if ($enabled) {
            try {
                // Prefilled payment country based on user and request (e.g. IP) data
                $countryResolution = $this->resolveCountry($user);
                if ($countryResolution) {
                    $prefilledCountryCode = $countryResolution->country->iso_code;
                    $prefilledCountryReason = $countryResolution->getReasonValue();
                }
            } catch (OneStopShopCountryConflictException $e) {
                Debugger::log($e,);
            }
        }
        $data = new OneStopShopFrontendData(
            enabled: $enabled,
            countries: $countries,
            prefilledCountryCode: $prefilledCountryCode,
            prefilledCountryReason: $prefilledCountryReason,
        );
        $this->frontendDataRequestCache[$userId] = $data;
        return $data;
    }

    public function getCountryVatRate(ActiveRow $paymentCountry, PaymentItemInterface|ActiveRow $paymentItem): float|int
    {
        $vatRates = $this->vatRatesRepository->getByCountry($paymentCountry);
        if (!$vatRates) {
            // If no VAT is recorded, return 0.
            //throw new \RuntimeException("Missing VAT rates for country [{$paymentCountry->iso_code}]");
            return 0;
        }

        $dataProviderParams = [
            'vat_rates' => $vatRates,
            'payment_item' => $paymentItem,
        ];

        /** @var OneStopShopVatRateDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            OneStopShopVatRateDataProviderInterface::PATH,
            OneStopShopVatRateDataProviderInterface::class
        );
        foreach ($providers as $provider) {
            $vatRate = $provider->provide($dataProviderParams);
            if ($vatRate !== null) {
                return $vatRate;
            }
        }

        return $vatRates->standard;
    }

    private function getIp(): string
    {
        return Request::getIp();
    }
}
