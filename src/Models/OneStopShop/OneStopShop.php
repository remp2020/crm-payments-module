<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\OneStopShop;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Request;
use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\Models\GeoIp\GeoIpInterface;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Table\ActiveRow;

final class OneStopShop
{
    public function __construct(
        private ApplicationConfig $applicationConfig,
        private GeoIpInterface $geoIp,
        private VatRatesRepository $vatRatesRepository,
        private PaymentItemsRepository $paymentItemsRepository,
        private DataProviderManager $dataProviderManager,
        private CountriesRepository $countriesRepository,
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
    ): ?CountryResolution {
        if (!$this->isEnabled()) {
            return null;
        }
        $dataProviderParams = compact(
            'user',
            'selectedCountryCode',
            'paymentAddress',
            'paymentItemContainer',
            'formParams',
            'ipAddress'
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

        if ($paymentAddress) {
            if ($selectedCountryCode !== null && $selectedCountryCode !== $paymentAddress->country->iso_code) {
                throw new OneStopShopCountryConflictException("Conflicting selectedCountryCode [{$selectedCountryCode}] and paymentAddress country [{$paymentAddress->country->iso_code}]");
            }
            return new CountryResolution($paymentAddress->country->iso_code, CountryResolutionType::PAYMENT_ADDRESS);
        }

        if ($selectedCountryCode) {
            return new CountryResolution($selectedCountryCode, CountryResolutionType::USER_SELECTED);
        }

        $ip = $ipAddress ?? $this->getIp();
        $ipCountryCode = $this->geoIp->countryCode($ip);

        if (!$ipCountryCode) {
            // Some IP addresses do not have designated country (e.g. 199.64.72.254).
            return null;
        }

        return new CountryResolution($ipCountryCode, CountryResolutionType::IP_ADDRESS);
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

        $vatRate = $this->getCountryVatRate($paymentCountry);

        foreach ($payment->related('payment_items') as $paymentItem) {
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

        // TODO: add chance to change VAT for external modules using data provider/event
        $vatRate = $this->getCountryVatRate($paymentCountry);

        foreach ($paymentItemContainer->items() as $item) {
            // Currently, VAT is stored as int in payment_items table
            $item->forceVat((int) $vatRate);
        }
    }

    public function getCountryVatRate(ActiveRow $paymentCountry): float|int
    {
        $vatRates = $this->vatRatesRepository->getByCountry($paymentCountry);
        if (!$vatRates) {
            // If no VAT is recorded, return 0.
            //throw new \RuntimeException("Missing VAT rates for country [{$paymentCountry->iso_code}]");
            return 0;
        }
        return $vatRates->standard;
    }

    private function getIp(): string
    {
        return Request::getIp();
    }
}
