<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\OneStopShop;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\Request;
use Crm\PaymentsModule\DataProviders\OneStopShopCountryResolutionDataProviderInterface;
use Crm\PaymentsModule\DataProviders\OneStopShopVatRateDataProviderInterface;
use Crm\PaymentsModule\Models\GeoIp\GeoIpException;
use Crm\PaymentsModule\Models\GeoIp\GeoIpInterface;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainerFactory;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemInterface;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Tracy\Debugger;

final class OneStopShop
{
    public const PAYMENT_META_ONE_STOP_SHOP = 'one_stop_shop';

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
        private readonly PaymentItemContainerFactory $paymentItemContainerFactory,
        private readonly SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->applicationConfig->get('one_stop_shop_enabled');
    }

    /**
     * @param ActiveRow|null $user
     * @param string|null $selectedCountryCode
     * @param ActiveRow|null $paymentAddress
     * @param PaymentItemContainer|null $paymentItemContainer
     * @param array|null $formParams
     * @param string|false|null $ipAddress Set to enforce specific IP address.
     *                                     If null, IP of current request will be used.
     *                                     If set to false, IP address will be ignored during resolving.
     * @param ActiveRow|null $previousPayment Set if there is a relevant previous payment, e.g. when doing recurrent
     *                                        payment
     * @param ActiveRow|null $payment Set only when resolving existing payment
     *
     * @return CountryResolution|null
     * @throws OneStopShopCountryConflictException
     * @throws DataProviderException
     */
    public function resolveCountry(
        ?ActiveRow $user = null,
        ?string $selectedCountryCode = null,
        ?ActiveRow $paymentAddress = null,
        ?PaymentItemContainer $paymentItemContainer = null,
        ?array $formParams = null,
        string|false|null $ipAddress = null,
        ?ActiveRow $previousPayment = null,
        ?ActiveRow $payment = null,
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
            'previousPayment',
            'payment',
        );

        /** @var OneStopShopCountryResolutionDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            OneStopShopCountryResolutionDataProviderInterface::PATH,
            OneStopShopCountryResolutionDataProviderInterface::class,
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

        if ($ipAddress) {
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
        }

        // If all other resolutions return no result and there is no conflict, default to default country
        return new CountryResolution($this->countriesRepository->defaultCountry(), CountryResolutionTypeEnum::DefaultCountry);
    }

    public function adjustPaymentVatRates(
        ActiveRow $payment,
        ?ActiveRow $paymentCountry,
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$paymentCountry) {
            return;
        }

        // adjusting payment vat rates for default country is done only if different payment country
        // was previously set on existing payment
        $defaultCountry = false;
        if ($this->countriesRepository->defaultCountry()->iso_code === $paymentCountry->iso_code) {
            if (!$payment->payment_country) {
                return;
            }
            $defaultCountry = true;
        }

        // First create container containing all items
        $paymentItemContainer = $this->paymentItemContainerFactory->createFromPayment($payment);

        // Use container with all items for VAT resolving
        foreach ($paymentItemContainer->items() as $paymentItem) {
            $vatRate = null;

            // When setting payment country back to default country, we can retrieve VAT rates directly from
            // subscription type items
            if ($defaultCountry && $paymentItem instanceof SubscriptionTypePaymentItem) {
                $subscriptionTypeItem = $this->subscriptionTypeItemsRepository->find($paymentItem->getSubscriptionTypeItemId());
                $vatRate = $subscriptionTypeItem?->vat;
            }

            if ($vatRate === null) {
                $vatRate = $this->getCountryVatRate($paymentCountry, $paymentItem, $paymentItemContainer);
            }

            $paymentItem->forceVat($vatRate);
        }

        // Remove and re-add all payment items - to correctly initialize related data such as revenues
        $this->paymentItemsRepository->deleteByPayment($payment);
        $this->paymentItemsRepository->add($payment, $paymentItemContainer);
    }

    public function adjustPaymentItemContainerVatRates(
        PaymentItemContainer $paymentItemContainer,
        ?ActiveRow $paymentCountry,
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        if (!$paymentCountry) {
            return;
        }

        if ($paymentItemContainer->preventOssVatChange()) {
            return;
        }

        // Do not adjust rates for default country
        if ($this->countriesRepository->defaultCountry()->iso_code === $paymentCountry->iso_code) {
            return;
        }

        foreach ($paymentItemContainer->items() as $paymentItem) {
            $vatRate = $this->getCountryVatRate($paymentCountry, $paymentItem, $paymentItemContainer);
            $paymentItem->forceVat($vatRate);
        }

        $paymentItemContainer->setPaymentMeta(self::PAYMENT_META_ONE_STOP_SHOP, 1);
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

    public function getCountryVatRate(
        ActiveRow $paymentCountry,
        PaymentItemInterface $paymentItem,
        PaymentItemContainer $paymentItemContainer = null,
    ): float {
        $vatRates = $this->vatRatesRepository->getByCountry($paymentCountry);
        if (!$vatRates) {
            // If no VAT is recorded, return 0.
            return 0;
        }

        $dataProviderParams = [
            'vat_rates' => $vatRates,
            'payment_item' => $paymentItem,
            'payment_item_container' => $paymentItemContainer,
        ];

        /** @var OneStopShopVatRateDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            OneStopShopVatRateDataProviderInterface::PATH,
            OneStopShopVatRateDataProviderInterface::class,
        );
        foreach ($providers as $provider) {
            $vatRate = $provider->provide($dataProviderParams);
            if ($vatRate !== null) {
                return $vatRate;
            }
        }

        if ($paymentItem instanceof DonationPaymentItem ||
            ($paymentItem instanceof ActiveRow && $paymentItem->type === DonationPaymentItem::TYPE)
        ) {
            return 0; // donation should return 0% VAT by default
        }

        return $vatRates->standard;
    }

    private function getIp(): string
    {
        return Request::getIp();
    }
}
