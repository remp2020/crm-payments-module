<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\ChangePaymentCountryDataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionTypeEnum;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Exception;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class PaymentCountryChangeFormFactory
{
    private const FIELD_PAYMENT_ID = 'payment_id';
    private const FIELD_NEW_COUNTRY_ID = 'new_country_id';
    private const FIELD_NEW_RESOLUTION_REASON = 'new_resolution_reason';

    private ActiveRow $payment;

    /**
     * @var callable(ActiveRow $payment): void|null
     */
    public $onConfirmation = null;

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly CountriesRepository $countriesRepository,
        private readonly OneStopShop $oneStopShop,
        private readonly Translator $translator,
        private readonly DataProviderManager $dataProviderManager,
    ) {
    }

    public function create(ActiveRow $payment): Form
    {
        $this->payment = $payment;

        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapInlineRenderer());

        try {
            $countryResolution = $this->getCountryResolution($payment);
        } catch (OneStopShopCountryConflictException) {
            $countryResolution = null;
        }

        if ($countryResolution !== null) {
            $form->addHidden(self::FIELD_NEW_COUNTRY_ID, $countryResolution->country->id ?? '')
                ->addRule(Form::Integer);

            $form->addHidden(self::FIELD_NEW_RESOLUTION_REASON, $countryResolution->getReasonValue() ?? '');
        }

        $isResolved = $countryResolution !== null;

        $submitButton = $form->addSubmit('change_country', 'payments.admin.change_payment_country.confirm_button')
            ->setDisabled(!$isResolved || !$this->shouldResolveCountry($payment, $countryResolution?->country));
        $submitButton->getControlPrototype()
            ->addAttributes(['class' => 'btn btn-danger btn-lg']);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded(Form $form, $values): void
    {
        $country = $this->countriesRepository->find($values[self::FIELD_NEW_COUNTRY_ID])
            ?? throw new Exception('Country not found.');

        $resolutionReason = CountryResolutionTypeEnum::tryFrom($values[self::FIELD_NEW_RESOLUTION_REASON] ?? '')?->value
            ?? $values[self::FIELD_NEW_RESOLUTION_REASON];

        if (!$this->shouldResolveCountry($this->payment, $country)) {
            $form->addError('payments.admin.change_payment_country.same_country_info_message');
            return;
        }

        try {
            $countryResolution = $this->getCountryResolution($this->payment);
        } catch (OneStopShopCountryConflictException) {
            $form->addError('payments.admin.change_payment_country.oss_conflict_error_message');
            return;
        }

        // Check if country and resolution reason are still the same as when the form was rendered (since we do not allow to change it through UI for now)
        if ($country->id !== $countryResolution->country->id ||
            $resolutionReason !== $countryResolution->getReasonValue()) {
            $form->addError('payments.admin.change_payment_country.country_resolution_changed_error_message');
            return;
        }

        /** @var ChangePaymentCountryDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.change_payment_country',
            ChangePaymentCountryDataProviderInterface::class
        );

        $this->paymentsRepository->getTransaction()->wrap(function () use ($providers, $countryResolution) {
            foreach ($providers as $provider) {
                $provider->changePaymentCountry($this->payment, $countryResolution);
            }
        });

        if (!$form->hasErrors() && $this->onConfirmation !== null) {
            ($this->onConfirmation)($this->payment);
        }
    }

    /**
     * @throws \Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException
     */
    public function getCountryResolution(ActiveRow $payment): ?CountryResolution
    {
        return $this->oneStopShop->resolveCountry(
            user: $payment->user,
            ipAddress: $payment->ip,
            payment: $payment,
        );
    }

    private function shouldResolveCountry(ActiveRow $payment, ?ActiveRow $country): bool
    {
        if ($country === null) {
            return false;
        }

        if ($payment->payment_country_id === null) {
            return true;
        }

        $isSameCountry = $country->id === $payment->payment_country_id;
        if ($isSameCountry) {
            return false;
        }

        return true;
    }
}
