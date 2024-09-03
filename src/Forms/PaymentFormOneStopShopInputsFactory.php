<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionTypeEnum;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\VatRate\VatRateValidator;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\Html;

final class PaymentFormOneStopShopInputsFactory
{

    public function __construct(
        private readonly OneStopShop $oneStopShop,
        private readonly Translator $translator,
        private readonly CountriesRepository $countriesRepository,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
        private readonly VatRatesRepository $vatRatesRepository,
        private readonly VatRateValidator $vatRateValidator,
    ) {
    }

    public function addInputs(Form $form, ?ActiveRow $user, ?ActiveRow $payment): void
    {
        if (!$this->oneStopShop->isEnabled()) {
            return;
        }

        $paymentCountryInputDescription = $this->translator->translate(
            'payments.form.payment.payment_country_id.description',
            [
                'country_name' => $this->countriesRepository->defaultCountry()->name,
            ]
        );
        $paymentCountryInput = $form->addSelect(
            'payment_country_id',
            $this->translator->translate('payments.form.payment.payment_country_id.label'),
            $this->countriesSelectItemsBuilder->getAllPairs(),
        )
            ->setOption('description', $paymentCountryInputDescription)
            ->setPrompt('--');
        $paymentCountryInput->setOption('id', 'payment-country-id');

        if ($payment && $payment->status !== PaymentsRepository::STATUS_FORM) {
            $paymentCountryInput->setDisabled();
        }

        if ($payment) {
            $form->addCheckbox('oss_force_vat_change', 'payments.form.payment.oss_force_vat_change.label')
                ->setOption('description', $this->translator->translate(
                    'payments.form.payment.oss_force_vat_change.description'
                ))
                ->setOption('id', 'oss-force-vat-change');
            $paymentCountryInput->addCondition(Form::NotEqual, $payment->payment_country_id)
                ->toggle('oss-force-vat-change');
        } else {
            // pre-filled OSS country
            $countryResolution = $this->oneStopShop->resolveCountry(user: $user, ipAddress: false);
            if ($countryResolution) {
                $prefilledReasonDescription = match ($countryResolution->reason) {
                    CountryResolutionTypeEnum::InvoiceAddress => $this->translator->translate(
                        'payments.form.payment.payment_country_id.prefilled_reason_invoice_address'
                    ),
                    CountryResolutionTypeEnum::DefaultCountry => $this->translator->translate(
                        'payments.form.payment.payment_country_id.prefilled_reason_default_country'
                    ),
                    default => $this->translator->translate(
                        'payments.form.payment.payment_country_id.prefilled_reason_other',
                        [
                            'reason' => $countryResolution->getReasonValue(),
                        ]
                    ),
                };

                $paymentCountryInput
                    ->setDefaultValue($countryResolution->country->id)
                    ->setOption('description', Html::el('span', ['class' => 'help-block'])
                        ->setHtml("<b>{$prefilledReasonDescription}</b><br />" . $paymentCountryInputDescription));
            }
        }
    }

    public function processInputs(
        PaymentItemContainer $paymentItemContainer,
        ActiveRow $user,
        ?ActiveRow $payment,
        ?ActiveRow $address,
        ArrayHash $values,
        Form $form,
        bool $allowEditPaymentItems,
        bool $customPaymentItems,
    ): ?CountryResolution {
        if (!$this->oneStopShop->isEnabled()) {
            return null;
        }

        $countryResolution = null;

        $ossForceVatChange = filter_var($values['oss_force_vat_change'] ?? null, FILTER_VALIDATE_BOOLEAN);
        unset($values['oss_force_vat_change']);

        if ($customPaymentItems || ($payment && $payment->status === 'form')) {
            // this means OSS will not change VAT rates on payment items
            if (!$ossForceVatChange) {
                $paymentItemContainer->setPreventOssVatChange();
            }
        }

        $selectedCountry = null;
        if (isset($values['payment_country_id'])) {
            $selectedCountry = $this->countriesRepository->find($values['payment_country_id']);
        }

        if ($this->shouldResolveCountry($payment, $selectedCountry)) {
            try {
                $countryResolution = $this->oneStopShop->resolveCountry(
                    user: $user,
                    selectedCountryCode: $selectedCountry?->iso_code,
                    paymentAddress: $address,
                    paymentItemContainer: $paymentItemContainer,
                    ipAddress: false // do not use IP address for resolution
                );

                // if admin explicitly selects a country, correct reason to AdminSelected
                if ($countryResolution && $selectedCountry) {
                    $countryResolution = new CountryResolution($countryResolution->country, CountryResolutionTypeEnum::AdminSelected);
                }
            } catch (OneStopShopCountryConflictException $exception) {
                $form->addError($this->translator->translate(
                    'payments.form.payment.one_stop_shop.conflict'
                ));
                return null;
            }
        }

        // Validate rates in case only when custom VAT rates may be applied, cases:
        // - 'form' payment + user forces VAT changes + edit of payment items is allowed OR forcing VAT changes
        // - new payment + custom payment items is checked
        if (($payment && $payment->status === 'form' && $allowEditPaymentItems && !$ossForceVatChange) ||
            (!$payment && $customPaymentItems)) {
            $this->validateVatRates($form, $paymentItemContainer, $countryResolution?->country ?? $payment?->payment_country);
        }

        return $countryResolution;
    }

    private function shouldResolveCountry(?ActiveRow $payment, ?ActiveRow $selectedCountry): bool
    {
        // do not resolve when not in 'form' or selected country is the same as actual payment country
        if ($payment) {
            if ($payment->status !== 'form') {
                return false;
            }

            if ($payment->payment_country && $selectedCountry && $selectedCountry->id === $payment->payment_country->id) {
                return false;
            }
        }
        return true;
    }

    private function validateVatRates(
        Form $form,
        PaymentItemContainer $paymentItemContainer,
        ?ActiveRow $country,
    ): void {
        $country ??= $this->countriesRepository->defaultCountry();
        $vatRatesRow = $this->vatRatesRepository->getByCountry($country);

        foreach ($paymentItemContainer->items() as $i => $item) {
            // Zero VAT for donations is fine at this moment. System doesn't allow to specify payment item type
            // manually, so this item had to come from some other place. The zero VAT was intentional, and it's OK
            // to allow it here.
            $allowZeroVatRate = $item->type() === DonationPaymentItem::TYPE || !$vatRatesRow;
            $isVatRateValid = $this->vatRateValidator->validate($vatRatesRow, (float) $item->vat(), $allowZeroVatRate);

            if (!$isVatRateValid) {
                $form['payment_items']->addError($this->translator->translate(
                    'payments.form.payment.one_stop_shop.vat_not_allowed',
                    [
                        'invalid_vat' => $item->vat() . '%',
                        'iterator' => $i + 1,
                        'country' => $country->name,
                    ]
                ));
            }
        }
    }
}
