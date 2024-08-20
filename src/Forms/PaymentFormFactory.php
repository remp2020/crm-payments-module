<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Forms\Controls\CountriesSelectItemsBuilder;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProviders\PaymentFormDataProviderInterface;
use Crm\PaymentsModule\Forms\Controls\SubscriptionTypesSelectItemsBuilder;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionTypeEnum;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\VatRate\VatRateValidator;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\VatRatesRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionTypeHelper;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Forms\Controls\AddressesSelectItemsBuilder;
use Crm\UsersModule\Repositories\AddressesRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\SubmitButton;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Tomaj\Form\Renderer\BootstrapRenderer;

class PaymentFormFactory
{
    const MANUAL_SUBSCRIPTION_START = 'start_at';
    const MANUAL_SUBSCRIPTION_START_END = 'start_end_at';

    public $onSave;

    public $onUpdate;

    private $onCallback;

    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly PaymentGatewaysRepository $paymentGatewaysRepository,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly UsersRepository $usersRepository,
        private readonly AddressesRepository $addressesRepository,
        private readonly DataProviderManager $dataProviderManager,
        private readonly ApplicationConfig $applicationConfig,
        private readonly Translator $translator,
        private readonly SubscriptionTypeHelper $subscriptionTypeHelper,
        private readonly SubscriptionTypesSelectItemsBuilder $subscriptionTypesSelectItemsBuilder,
        private readonly AddressesSelectItemsBuilder $addressesSelectItemsBuilder,
        private readonly OneStopShop $oneStopShop,
        private readonly CountriesRepository $countriesRepository,
        private readonly VatRatesRepository $vatRatesRepository,
        private readonly VatRateValidator $vatRateValidator,
        private readonly CountriesSelectItemsBuilder $countriesSelectItemsBuilder,
    ) {
    }

    /**
     * @param int $paymentId
     * @param ActiveRow $user
     * @return Form
     * @throws DataProviderException
     * @throws JsonException
     */
    public function create($paymentId, ActiveRow $user = null)
    {
        $defaults = [
            'additional_type' => 'single',
        ];
        $payment = null;

        if ($paymentId) {
            $payment = $this->paymentsRepository->find($paymentId);
            $defaults = $payment->toArray();
            $items = [];
            foreach ($payment->related('payment_items')->fetchAll() as $paymentItem) {
                $item = [
                    'amount' => $paymentItem->amount,
                    'count' => $paymentItem->count,
                    'name' => $paymentItem->name,
                    'vat' => $paymentItem->vat,
                    'type' => $paymentItem->type,
                    'meta' => $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value'),
                ];
                // TODO: temporary solution until whole form is refactored and fields handled by dataproviders
                if (isset($paymentItem->postal_fee_id)) {
                    $item['postal_fee_id'] = $paymentItem->postal_fee_id;
                }
                if (isset($paymentItem->product_id)) {
                    $item['product_id'] = $paymentItem->product_id;
                }
                if (isset($paymentItem->subscription_type_id)) {
                    $item['subscription_type_id'] = $paymentItem->subscription_type_id;
                    $item['subscription_type_item_id'] = $paymentItem->subscription_type_item_id ?? null;
                }
                $items[] = $item;
            }
            $defaults['payment_items'] = Json::encode($items);

            if (isset($defaults['subscription_end_at']) && isset($defaults['subscription_start_at'])) {
                $defaults['manual_subscription'] = self::MANUAL_SUBSCRIPTION_START_END;
            } elseif (isset($defaults['subscription_start_at'])) {
                $defaults['manual_subscription'] = self::MANUAL_SUBSCRIPTION_START;
            }
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->onSuccess[] = [$this, 'formSucceeded'];

        $form->addGroup('');

        $paymentGateways = $this->paymentGatewaysRepository->all()->fetchPairs('id', 'name');
        if ($payment) {
            $paymentGateways[$payment->payment_gateway_id] = $payment->payment_gateway->name;
        }
        $form->addSelect('payment_gateway_id', 'payments.form.payment.payment_gateway_id.label', $paymentGateways)
            ->setRequired('payments.form.payment_gateway.required');

        $variableSymbol = $form->addText('variable_symbol', 'payments.form.payment.variable_symbol.label')
            ->setRequired('payments.form.payment.variable_symbol.required')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.variable_symbol.placeholder');

        if (!$paymentId) {
            $variableSymbol->setOption(
                'description',
                Html::el('a', ['href' => '/api/v1/payments/variable-symbol', 'class' => 'variable_symbol_generate'])
                    ->setHtml($this->translator->translate('payments.form.payment.variable_symbol.generate'))
            )->addRule(function (TextInput $control) {
                $paymentRow = $this->paymentsRepository->findLastByVS($control->getValue());
                if ($paymentRow) {
                    return $paymentRow->created_at < new DateTime('-15 minutes');
                }
                return true;
            }, 'payments.form.payment.variable_symbol.already_used');
        }

        $form->addText('amount', 'payments.form.payment.amount.label')
            ->setRequired('payments.form.payment.amount.required')
            ->setHtmlAttribute('readonly', 'readonly')
            ->addRule(Form::MIN, 'payments.form.payment.amount.nonzero', 0.01)
            ->setOption(
                'description',
                Html::el('span', ['class' => 'help-block'])
                    ->setHtml($this->translator->translate('payments.form.payment.amount.description'))
            );

        // One Stop Shop

        if ($this->oneStopShop->isEnabled()) {
            $countryResolution = $this->oneStopShop->resolveCountry(user: $user, ipAddress: false);

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
                                'reason' => $countryResolution->getReasonValue()
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

        // subscription types and items

        $form->addGroup('payments.form.payment.items');

        $subscriptionTypeOptions = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        if (isset($payment->subscription_type)) {
            $subscriptionTypeOptions[] = $payment->subscription_type;
        }
        $subscriptionTypes = $this->subscriptionTypeHelper->getItems($subscriptionTypeOptions);

        $form->addHidden('subscription_types', Json::encode($subscriptionTypes));

        $subscriptionType = $form->addSelect(
            'subscription_type_id',
            'payments.form.payment.subscription_type_id.label',
            $this->subscriptionTypesSelectItemsBuilder->buildWithDescription($subscriptionTypeOptions)
        )->setPrompt("payments.form.payment.subscription_type_id.prompt");
        $subscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        if (!$payment) {
            $form->addCheckbox('custom_payment_items', 'payments.form.payment.custom_payment_items.label')->setOption('id', 'custom_payment_items');
        }

        $form->addHidden('payment_items');

        /** @var PaymentFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.payment_form', PaymentFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'payment' => $payment]);
        }

        if ($payment) {
            $subscriptionType->setHtmlAttribute('readonly', 'readonly');
        } else {
            $form->addText('additional_amount', 'payments.form.payment.additional_amount.label')
                ->setNullable()
                ->setHtmlAttribute('placeholder', 'payments.form.payment.additional_amount.placeholder');

            $form->addSelect('additional_type', 'payments.form.payment.additional_type.label', [
                'single' => $this->translator->translate('payments.form.payment.additional_type.single'),
                'recurrent' => $this->translator->translate('payments.form.payment.additional_type.recurrent'),
            ])
                ->setOption('description', 'payments.form.payment.additional_type.description')
                ->setDisabled(['recurrent']);
        }

        $form->addGroup('payments.form.payment.general_settings');

        $statusPairs = $this->paymentsRepository->getStatusPairs();
        if ($payment && !isset($statusPairs[$payment->status])) {
            $statusPairs[$payment->status] = $payment->status;
        }
        $status = $form->addSelect('status', 'payments.form.payment.status.label', $statusPairs);

        $paidAt = $form->addText('paid_at', 'payments.form.payment.paid_at.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.paid_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1")
            ->setHtmlAttribute('flatpickr_maxdatetime', (new DateTime())->format(DateTime::ATOM));
        $paidAt->setOption('id', 'paid-at');
        $paidAt->addConditionOn($status, Form::EQUAL, PaymentsRepository::STATUS_PAID)
            ->setRequired('payments.form.payment.paid_at.required');

        $paidAt = $form->addCheckbox('send_notification', 'payments.form.payment.send_notification.label');
        $paidAt->setOption('id', 'send-notification');

        $status->addCondition(Form::EQUAL, PaymentsRepository::STATUS_PAID)->toggle('paid-at');
        $status->addCondition(Form::EQUAL, PaymentsRepository::STATUS_PAID)->toggle('send-notification');

        $manualSubscription = $form->addSelect('manual_subscription', 'payments.form.payment.manual_subscription.label', [
            self::MANUAL_SUBSCRIPTION_START => $this->translator->translate('payments.form.payment.manual_subscription.start'),
            self::MANUAL_SUBSCRIPTION_START_END => $this->translator->translate('payments.form.payment.manual_subscription.start_end'),
        ])->setPrompt('payments.form.payment.manual_subscription.prompt');

        $manualSubscription->addCondition(Form::EQUAL, self::MANUAL_SUBSCRIPTION_START)->toggle('subscription-start-at');
        $manualSubscription->addCondition(Form::EQUAL, self::MANUAL_SUBSCRIPTION_START_END)->toggle('subscription-start-at');
        $manualSubscription->addCondition(Form::EQUAL, self::MANUAL_SUBSCRIPTION_START_END)->toggle('subscription-end-at');

        $subscriptionStartAt = $form->addText('subscription_start_at', 'payments.form.payment.subscription_start_at.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.subscription_start_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1")
            ->setOption('id', 'subscription-start-at')
            ->setOption('description', 'payments.form.payment.subscription_start_at.description')
            ->setNullable()
            ->setRequired(false);

        $subscriptionStartAt
            ->addConditionOn($manualSubscription, Form::EQUAL, self::MANUAL_SUBSCRIPTION_START)
            ->setRequired(true);
        $subscriptionStartAt
            ->addConditionOn($manualSubscription, Form::EQUAL, self::MANUAL_SUBSCRIPTION_START_END)
            ->setRequired(true);
        $subscriptionStartAt
            ->addConditionOn($manualSubscription, Form::IS_IN, [
                self::MANUAL_SUBSCRIPTION_START,
                self::MANUAL_SUBSCRIPTION_START_END,
            ])
            ->addRule(function (TextInput $field, $user) {
                return DateTime::from($field->getValue()) >= new DateTime('today midnight');
            }, 'payments.form.payment.subscription_start_at.not_past', $user);

        $subscriptionEndAt = $form->addText('subscription_end_at', 'payments.form.payment.subscription_end_at.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.subscription_end_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1")
            ->setOption('id', 'subscription-end-at')
            ->setOption('description', 'payments.form.payment.subscription_end_at.description')
            ->setNullable()
            ->setRequired(false);

        $subscriptionEndAt
            ->addConditionOn($manualSubscription, Form::EQUAL, self::MANUAL_SUBSCRIPTION_START_END)
            ->setRequired()
            ->addRule(function (TextInput $field, $user) {
                return DateTime::from($field->getValue()) >= new DateTime();
            }, 'payments.form.payment.subscription_end_at.not_past', $user);

        // allow change of manual subscription start & end dates only for 'form' payments
        if ($payment && $payment->status !== 'form') {
            $manualSubscription
                ->setHtmlAttribute('readonly', 'readonly')
                ->setDisabled();
            $subscriptionStartAt
                ->setHtmlAttribute('readonly', 'readonly')
                ->setDisabled();
            $subscriptionEndAt
                ->setHtmlAttribute('readonly', 'readonly')
                ->setDisabled();
        }

        $form->addTextArea('note', 'payments.form.payment.note.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.note.placeholder')
            ->getControlPrototype()->addAttributes(['class' => 'autosize']);

        $form->addText('referer', 'payments.form.payment.referer.label')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'payments.form.payment.referer.placeholder');

        $addresses = $this->addressesSelectItemsBuilder->buildSimpleWithTypes($user);
        if (count($addresses) > 0) {
            $form->addSelect('address_id', "payments.form.payment.address_id.label", $addresses)->setPrompt('--');
        }

        $form->addHidden('user_id', $user->id);

        $form->addSubmit('send', 'payments.form.payment.send')
            ->setHtmlAttribute('class', 'btn btn-primary')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('payments.form.payment.send'));

        $form->addSubmit('send_and_close', 'payments.form.payment.send_and_close')
            ->setHtmlAttribute('class', 'btn btn-primary')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('payments.form.payment.send'));

        if ($payment) {
            $form->addHidden('payment_id', $payment->id);
        }

        $form->setDefaults($defaults);
        $form->onSuccess[] = [$this, 'callback'];

        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        $values = clone($values);

        $sendNotification = $values['send_notification'];
        if ($values['status'] === PaymentsRepository::STATUS_REFUND) {
            $sendNotification = true;
        }

        unset($values['subscription_types'], $values['send_notification']);

        if (empty($values['additional_amount'])) {
            // treat all empty values as null
            $values['additional_amount'] = null;
        }

        $payment = null;
        if (isset($values['payment_id'])) {
            $payment = $this->paymentsRepository->find($values['payment_id']);
        }
        unset($values['payment_id']);

        $user = $this->usersRepository->find($values['user_id']);
        $paymentGateway = $this->paymentGatewaysRepository->find($values['payment_gateway_id']);
        $subscriptionType = null;
        if (isset($values['subscription_type_id'])) {
            $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
        }
        $selectedCountry = null;
        if (isset($values['payment_country_id'])) {
            $selectedCountry = $this->countriesRepository->find($values['payment_country_id']);
        }
        $address = null;
        if (isset($values['address_id'])) {
            $address = $this->addressesRepository->find($values['address_id']);
        }
        [$subscriptionStartAt, $subscriptionEndAt] = $this->resolveSubscriptionStartAndEnd($values);

        $values['paid_at'] = !empty($values['paid_at']) ? DateTime::from(strtotime($values['paid_at'])) : null;
        if ($values['paid_at'] > new DateTime()) {
            $form['paid_at']->addError('payments.form.payment.paid_at.no_future_paid_at');
        }

        $ossForceVatChange = filter_var($values['oss_force_vat_change'] ?? null, FILTER_VALIDATE_BOOLEAN);
        $customPaymentItems = filter_var($values['custom_payment_items'] ?? null, FILTER_VALIDATE_BOOLEAN);
        unset($values['custom_payment_items'], $values['oss_force_vat_change']);

        [$paymentItemContainer, $allowEditPaymentItems] = $this->createPaymentItemContainer(
            form: $form,
            subscriptionType: $subscriptionType,
            payment: $payment,
            values: $values,
            ossForceVatChange: $ossForceVatChange,
            customPaymentItems: $customPaymentItems,
        );

        if ($form->hasErrors()) {
            return;
        }

        /** @var PaymentFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.payment_form',
            PaymentFormDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $paymentItemContainer->addItems($provider->paymentItems([
                'values' => $values,
            ]));
        }

        if ($payment !== null) {
            unset($values['payment_items']);

            $currentStatus = $payment->status;
            $newStatus = $values['status'];

            // if edit form doesn't contain donation form fields, set them from payment
            $values['additional_amount'] ??=  $payment->additional_amount;
            $values['additional_type'] ??= $payment->additional_type;

            if ($values['additional_amount']) {
                $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
                if ($donationPaymentVat === null) {
                    throw new \RuntimeException("Config 'donation_vat_rate' is not set");
                }
                $paymentItemContainer->addItem(new DonationPaymentItem($this->translator->translate('payments.admin.donation'), (float) $values['additional_amount'], (int) $donationPaymentVat));
            }

            // we don't want to update subscription dates on payment if it's already paid
            if ($currentStatus === PaymentsRepository::STATUS_FORM) {
                $values['subscription_start_at'] = $subscriptionStartAt;
                $values['subscription_end_at'] = $subscriptionEndAt;
            }

            // Unset array values or update fails.
            // Can be utilized by data providers which can store components within containers
            // which results in $values['container_name']['component_name'].
            // See commit message for details.
            foreach ($values as $i => $value) {
                if ($value instanceof ArrayHash) {
                    unset($values[$i]);
                }
            }

            // We don't want "status" to be updated in mass update(), but rather via separate updateStatus() call.
            unset($values['status']);

            if ($payment && $payment->status === 'form' && $allowEditPaymentItems) {
                // OSS resolution is allowed only if payment is in 'form' state
                $countryResolution = null;
                try {
                    $countryResolution  = $this->oneStopShop->resolveCountry(
                        user: $user,
                        selectedCountryCode: $selectedCountry?->iso_code,
                        paymentAddress: $address,
                        paymentItemContainer: $paymentItemContainer,
                        ipAddress: false // do not use IP address for resolution
                    );
                    if ($countryResolution) {
                        $values['payment_country_id'] = $countryResolution->country->id;
                        // if admin explicitly selects a country, correct reason to AdminSelected
                        $values['payment_country_resolution_reason'] = $selectedCountry ?
                            CountryResolutionTypeEnum::AdminSelected->value :
                            $countryResolution->getReasonValue();
                    }
                } catch (OneStopShopCountryConflictException $exception) {
                    $form->addError($this->translator->translate(
                        'payments.form.payment.one_stop_shop.conflict'
                    ));
                    return;
                }

                if (!$ossForceVatChange) {
                    $this->validateVatRates($form, $paymentItemContainer, $countryResolution?->country);
                }
                if ($form->hasErrors()) {
                    return;
                }
                $this->paymentsRepository->update($payment, $values, $paymentItemContainer);
            } else {
                $this->paymentsRepository->update($payment, $values);
            }

            if ($currentStatus !== $newStatus) {
                $this->paymentsRepository->updateStatus($payment, $newStatus, $sendNotification);
            }

            $this->onCallback = function () use ($form, $payment) {
                /** @var SubmitButton $saveAndCloseButton */
                $saveAndCloseButton = $form['send_and_close'];
                $this->onUpdate->__invoke($form, $payment, $saveAndCloseButton->isSubmittedBy());
            };
        } else {
            $variableSymbol = null;
            if ($values->variable_symbol) {
                $variableSymbol = $values->variable_symbol;
            }

            $additionalType = $values['additional_type'] ?? null;
            $additionalAmount = $values['additional_amount'] ?? null;
            if ($additionalAmount) {
                $additionalAmount = (float) str_replace(",", ".", $additionalAmount);
            }

            if ($additionalAmount) {
                $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
                if ($donationPaymentVat === null) {
                    throw new \Exception("Config 'donation_vat_rate' is not set");
                }
                $paymentItemContainer->addItem(
                    new DonationPaymentItem(
                        $this->translator->translate('payments.admin.donation'),
                        $additionalAmount,
                        (int) $donationPaymentVat
                    )
                );
            }

            $countryResolutionReason = null;
            try {
                $countryResolution  = $this->oneStopShop->resolveCountry(
                    user: $user,
                    selectedCountryCode: $selectedCountry?->iso_code,
                    paymentAddress: $address,
                    paymentItemContainer: $paymentItemContainer,
                    ipAddress: false // do not use IP address for resolution
                );
                if ($countryResolution && $selectedCountry) {
                    // if admin explicitly selects a country, correct reason to AdminSelected
                    $countryResolutionReason = $selectedCountry ?
                        CountryResolutionTypeEnum::AdminSelected->value :
                        $countryResolution->getReasonValue();
                }
            } catch (OneStopShopCountryConflictException $exception) {
                $form->addError($this->translator->translate(
                    'payments.form.payment.one_stop_shop.conflict'
                ));
                return;
            }

            if ($customPaymentItems) {
                $this->validateVatRates($form, $paymentItemContainer, $countryResolution?->country);
            }

            if ($form->hasErrors()) {
                return;
            }

            $payment = $this->paymentsRepository->add(
                subscriptionType: $subscriptionType,
                paymentGateway: $paymentGateway,
                user: $user,
                paymentItemContainer: $paymentItemContainer,
                referer: $values['referer'],
                subscriptionStartAt: $subscriptionStartAt,
                subscriptionEndAt: $subscriptionEndAt,
                note: $values['note'],
                additionalAmount: $additionalAmount,
                additionalType: $additionalType,
                variableSymbol: $variableSymbol,
                address: $address,
                paymentCountry: $countryResolution?->country,
                paymentCountryResolutionReason: $countryResolutionReason,
            );

            $updateArray = [];
            if (isset($values['paid_at'])) {
                $updateArray['paid_at'] = $values['paid_at'];
            }
            $this->paymentsRepository->update($payment, $updateArray);

            $this->paymentsRepository->updateStatus($payment, $values['status'], $sendNotification);

            $this->onCallback = function () use ($form, $payment) {
                /** @var SubmitButton $saveAndCloseButton */
                $saveAndCloseButton = $form['send_and_close'];
                $this->onSave->__invoke($form, $payment, $saveAndCloseButton->isSubmittedBy());
            };
        }
    }

    private function createPaymentItemContainer(
        Form $form,
        ?ActiveRow $subscriptionType,
        ?ActiveRow $payment,
        $values,
        bool $ossForceVatChange,
        bool $customPaymentItems
    ): array {
        $paymentItemContainer = new PaymentItemContainer();

        $allowEditPaymentItems = true;
        if ($customPaymentItems || ($payment && $payment->status === 'form')) {
            // this means OSS will not change VAT rates on payment items
            if (!$ossForceVatChange) {
                $paymentItemContainer->setPreventOssVatChange();
            }

            foreach (Json::decode($values->payment_items) as $i => $item) {
                $iterator = $i + 1;
                if ($payment && $item->type !== SubscriptionTypePaymentItem::TYPE) {
                    $allowEditPaymentItems = false;
                }
                if ($item->amount == 0 || $item->count == 0) {
                    continue;
                }
                if ($item->amount < 0) {
                    $form['subscription_type_id']->addError('payments.form.payment.subscription_type_id.negative_items_amount');
                }
                if (strlen($item->name) === 0) {
                    $form['payment_items']->addError($this->translator->translate(
                        'payments.form.payment.payment_items.name.required',
                        [
                            'iterator' => $iterator,
                        ]
                    ));
                }
                if (strlen($item->vat) === 0) {
                    $form['payment_items']->addError($this->translator->translate(
                        'payments.form.payment.payment_items.vat.required',
                        [
                            'iterator' => $iterator,
                        ]
                    ));
                }
                if ($form->hasErrors()) {
                    return [$paymentItemContainer, $allowEditPaymentItems];
                }

                if ($subscriptionType && $item->type === SubscriptionTypePaymentItem::TYPE) {
                    $meta = [];
                    if ($item->meta) {
                        if (is_string($item->meta)) {
                            $meta = trim($item->meta, "\"");
                            $meta = Json::decode($meta, true);
                        } else {
                            $meta = (array) $item->meta;
                        }
                    }

                    $amount = str_replace(",", ".", $item->amount);

                    $paymentItem = new SubscriptionTypePaymentItem(
                        $item->subscription_type_id,
                        $item->name,
                        (float) $amount,
                        $item->vat,
                        $item->count,
                        $meta,
                        $item->subscription_type_item_id,
                    );
                    $paymentItemContainer->addItem($paymentItem);
                }
            }
        } elseif ($subscriptionType) {
            $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        }

        return [$paymentItemContainer, $allowEditPaymentItems];
    }

    private function validateVatRates(
        Form $form,
        PaymentItemContainer $paymentItemContainer,
        ?ActiveRow $country,
    ): void {
        if (!$this->oneStopShop->isEnabled()) {
            return;
        }

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

    public function callback()
    {
        if ($this->onCallback) {
            $this->onCallback->__invoke();
        }
    }

    private function resolveSubscriptionStartAndEnd(mixed $values): array
    {
        $subscriptionStartAt = null;
        $subscriptionEndAt = null;
        if (isset($values['manual_subscription'])) {
            if ($values['manual_subscription'] === self::MANUAL_SUBSCRIPTION_START) {
                if ($values['subscription_start_at'] === null) {
                    throw new \Exception("manual subscription start attempted without providing start date");
                }
                $subscriptionStartAt = DateTime::from($values['subscription_start_at']);
            } elseif ($values['manual_subscription'] === self::MANUAL_SUBSCRIPTION_START_END) {
                if ($values['subscription_start_at'] === null) {
                    throw new \Exception("manual subscription start attempted without providing start date");
                }
                $subscriptionStartAt = DateTime::from($values['subscription_start_at']);
                if ($values['subscription_end_at'] === null) {
                    throw new \Exception("manual subscription end attempted without providing end date");
                }
                $subscriptionEndAt = DateTime::from($values['subscription_end_at']);
            }
        }
        unset($values['subscription_end_at'], $values['subscription_start_at'], $values['manual_subscription']);
        return [$subscriptionStartAt, $subscriptionEndAt];
    }
}
