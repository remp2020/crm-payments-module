<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProvider\PaymentFormDataProviderInterface;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\SubscriptionTypeHelper;
use Crm\UsersModule\Repository\AddressesRepository;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\TextInput;
use Nette\Localization\Translator;
use Nette\Utils\ArrayHash;
use Nette\Utils\DateTime;
use Nette\Utils\Html;
use Nette\Utils\Json;
use Tomaj\Form\Renderer\BootstrapRenderer;

class PaymentFormFactory
{
    const MANUAL_SUBSCRIPTION_START = 'start_at';
    const MANUAL_SUBSCRIPTION_START_END = 'start_end_at';

    private $paymentsRepository;

    private $paymentGatewaysRepository;

    private $subscriptionTypesRepository;

    private $usersRepository;

    private $addressesRepository;

    private $dataProviderManager;

    private $applicationConfig;

    private $translator;

    private $subscriptionTypeHelper;

    public $onSave;

    public $onUpdate;

    private $onCallback;

    private $subscriptionTypeItemMetaRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        SubscriptionTypeItemMetaRepository $subscriptionTypeItemMetaRepository,
        UsersRepository $usersRepository,
        AddressesRepository $addressesRepository,
        DataProviderManager $dataProviderManager,
        ApplicationConfig $applicationConfig,
        Translator $translator,
        SubscriptionTypeHelper $subscriptionTypeHelper
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->usersRepository = $usersRepository;
        $this->addressesRepository = $addressesRepository;
        $this->dataProviderManager = $dataProviderManager;
        $this->applicationConfig = $applicationConfig;
        $this->translator = $translator;
        $this->subscriptionTypeItemMetaRepository = $subscriptionTypeItemMetaRepository;
        $this->subscriptionTypeHelper = $subscriptionTypeHelper;
    }

    /**
     * @param int $paymentId
     * @param ActiveRow $user
     * @return Form
     * @throws \Crm\ApplicationModule\DataProvider\DataProviderException
     * @throws \Nette\Utils\JsonException
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
                    'meta' => $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value')
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

        // subscription types and items

        $form->addGroup('payments.form.payment.items');

        $subscriptionTypeOptions = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        if (isset($payment->subscription_type)) {
            $subscriptionTypeOptions[] = $payment->subscription_type;
        }
        $subscriptionTypes = $this->subscriptionTypeHelper->getItems($subscriptionTypeOptions);
        $subscriptionTypePairs = $this->subscriptionTypeHelper->getPairs($subscriptionTypeOptions, true);

        $form->addHidden('subscription_types', Json::encode($subscriptionTypes));

        $subscriptionType = $form->addSelect(
            'subscription_type_id',
            'payments.form.payment.subscription_type_id.label',
            $subscriptionTypePairs
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
                ->setHtmlAttribute('placeholder', 'payments.form.payment.additional_amount.placeholder');

            $form->addSelect('additional_type', 'payments.form.payment.additional_type.label', [
                'single' => $this->translator->translate('payments.form.payment.additional_type.single'),
                'recurrent' => $this->translator->translate('payments.form.payment.additional_type.recurrent')
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
                self::MANUAL_SUBSCRIPTION_START_END
            ])
            ->addRule(function (TextInput $field, $user) {
                if (DateTime::from($field->getValue()) < new DateTime('today midnight')) {
                    return false;
                }
                return true;
            }, 'payments.form.payment.subscription_start_at.not_past', $user);

        $subscriptionEndAt = $form->addText('subscription_end_at', 'payments.form.payment.subscription_end_at.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment.subscription_end_at.placeholder')
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime', "1")
            ->setOption('id', 'subscription-end-at')
            ->setOption('description', 'payments.form.payment.subscription_end_at.description')
            ->setRequired(false);

        $subscriptionEndAt
            ->addConditionOn($manualSubscription, Form::EQUAL, self::MANUAL_SUBSCRIPTION_START_END)
            ->setRequired(true)
            ->addRule(function (TextInput $field, $user) {
                if (DateTime::from($field->getValue()) < new DateTime()) {
                    return false;
                }
                return true;
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
            ->setHtmlAttribute('placeholder', 'payments.form.payment.referer.placeholder');

        $addresses = $this->addressesRepository->addressesSelect($user, false);
        if (count($addresses) > 0) {
            $form->addSelect('address_id', "payments.form.payment.address_id.label", $addresses)->setPrompt('--');
        }

        $form->addHidden('user_id', $user->id);

        $form->addSubmit('send', 'payments.form.payment.send')
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

        $subscriptionType = null;
        $subscriptionStartAt = null;
        $subscriptionEndAt = null;
        $sendNotification = $values['send_notification'];

        if ($values['status'] === PaymentsRepository::STATUS_REFUND) {
            $sendNotification = true;
        }

        unset($values['subscription_types']);
        unset($values['send_notification']);

        if ($values['subscription_type_id']) {
            $subscriptionType = $this->subscriptionTypesRepository->find($values['subscription_type_id']);
        }

        if (!isset($values['subscription_start_at']) || $values['subscription_start_at'] == '') {
            $values['subscription_start_at'] = null;
        }
        if (!isset($values['subscription_end_at']) || $values['subscription_end_at'] == '') {
            $values['subscription_end_at'] = null;
        }

        if (!isset($values['additional_amount']) || $values['additional_amount'] == '0' || $values['additional_amount'] == '') {
            $values['additional_amount'] = null;
        }

        if ($values['referer'] == '') {
            $values['referer'] = null;
        }

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
        unset($values['subscription_end_at']);
        unset($values['subscription_start_at']);
        unset($values['manual_subscription']);

        $paymentGateway = $this->paymentGatewaysRepository->find($values['payment_gateway_id']);

        if (isset($values['paid_at'])) {
            if ($values['paid_at']) {
                $values['paid_at'] = DateTime::from(strtotime($values['paid_at']));

                if ($values['paid_at'] > new DateTime()) {
                    $form['paid_at']->addError('payments.form.payment.paid_at.no_future_paid_at');
                }
            } else {
                $values['paid_at'] = null;
            }
        }

        $payment = null;
        if (isset($values['payment_id'])) {
            $payment = $this->paymentsRepository->find($values['payment_id']);
            unset($values['payment_id']);
        }

        $paymentItemContainer = new PaymentItemContainer();

        $allowEditPaymentItems = true;
        if ((isset($values['custom_payment_items']) && $values['custom_payment_items'])
            || ($payment && $payment->status === 'form')
        ) {
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
                    return;
                }

                if ($subscriptionType && $item->type === SubscriptionTypePaymentItem::TYPE) {
                    $meta = [];
                    if ($item->meta) {
                        if (is_string($item->meta)) {
                            $meta = trim($item->meta, "\"");
                            $meta = Json::decode($meta, Json::FORCE_ARRAY);
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
                        $item->subscription_type_item_id
                    );
                    $paymentItemContainer->addItem($paymentItem);
                }
            }
        } else {
            if ($subscriptionType) {
                $paymentItemContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
            }
        }

        /** @var PaymentFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.payment_form', PaymentFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $paymentItemContainer->addItems($provider->paymentItems([
                'values' => $values,
            ]));
        }

        if ($payment !== null) {
            unset($values['payment_items']);

            $currentStatus = $payment->status;

            // edit form doesn't contain donation form fields, set them from payment
            if (!isset($values['additional_amount']) || is_null($values['additional_amount'])) {
                $values['additional_amount'] = $payment->additional_amount;
            }
            if (!isset($values['additional_type']) || is_null($values['additional_type'])) {
                $values['additional_type'] = $payment->additional_type;
            }

            if ($values['additional_amount']) {
                $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
                if ($donationPaymentVat === null) {
                    throw new \Exception("Config 'donation_vat_rate' is not set");
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

            if ($payment && $payment->status === 'form' && $allowEditPaymentItems) {
                $this->paymentsRepository->update($payment, $values, $paymentItemContainer);
            } else {
                $this->paymentsRepository->update($payment, $values);
            }

            if ($currentStatus !== $values['status']) {
                $this->paymentsRepository->updateStatus($payment, $values['status'], $sendNotification);
            }

            $this->onCallback = function () use ($form, $payment) {
                $this->onUpdate->__invoke($form, $payment);
            };
        } else {
            $address = null;
            if (isset($values['address_id']) && $values['address_id']) {
                $address = $this->addressesRepository->find($values['address_id']);
            }
            $variableSymbol = null;
            if ($values->variable_symbol) {
                $variableSymbol = $values->variable_symbol;
            }

            $additionalType = null;
            $additionalAmount = null;
            if (isset($values['additional_amount']) && $values['additional_amount']) {
                $additionalAmount = (float) str_replace(",", ".", $values['additional_amount']);
            }
            if (isset($values['additional_type']) && $values['additional_type']) {
                $additionalType = $values['additional_type'];
            }

            if ($additionalAmount) {
                $donationPaymentVat = $this->applicationConfig->get('donation_vat_rate');
                if ($donationPaymentVat === null) {
                    throw new \Exception("Config 'donation_vat_rate' is not set");
                }
                $paymentItemContainer->addItem(new DonationPaymentItem($this->translator->translate('payments.admin.donation'), (float) $additionalAmount, (int) $donationPaymentVat));
            }

            $user = $this->usersRepository->find($values['user_id']);

            $payment = $this->paymentsRepository->add(
                $subscriptionType,
                $paymentGateway,
                $user,
                $paymentItemContainer,
                $values['referer'],
                null,
                $subscriptionStartAt,
                $subscriptionEndAt,
                $values['note'],
                $additionalAmount,
                $additionalType,
                $variableSymbol,
                $address,
                false
            );

            $updateArray = [];
            if (isset($values['paid_at'])) {
                $updateArray['paid_at'] = $values['paid_at'];
            }
            $this->paymentsRepository->update($payment, $updateArray);

            $this->paymentsRepository->updateStatus($payment, $values['status'], $sendNotification);

            $this->onCallback = function () use ($form, $payment) {
                $this->onSave->__invoke($form, $payment);
            };
        }
    }

    public function callback()
    {
        if ($this->onCallback) {
            $this->onCallback->__invoke();
        }
    }
}
