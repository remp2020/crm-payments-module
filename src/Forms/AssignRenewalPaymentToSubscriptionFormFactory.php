<?php

namespace Crm\PaymentsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\DataProviders\AssignRenewalPaymentToSubscriptionFormDataProviderInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\Payment\RenewalPayment;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Utils\Html;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AssignRenewalPaymentToSubscriptionFormFactory
{
    private const PAYMENT_MODE = 'payment';
    public $onSave;

    public function __construct(
        private readonly Translator $translator,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
        private readonly PriceHelper $priceHelper,
        private readonly RenewalPayment $renewalPayment,
        private readonly DataProviderManager $dataProviderManager,
    ) {
    }

    public function create(string $subscriptionId): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer(novalidate: false));

        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        $form->addHidden('subscription_id')->setRequired();

        $form->addGroup();
        $form->addSelect(
            'mode',
            'payments.admin.component.assign_renewal_payment_subscription_form.mode.label',
            [
                self::PAYMENT_MODE => 'payments.admin.component.assign_renewal_payment_subscription_form.mode.options.payment',
            ],
        )
            ->addCondition($form::Equal, self::PAYMENT_MODE)
            ->toggle('#renewal_payment_id')
            ->endCondition();

        /** @var AssignRenewalPaymentToSubscriptionFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.assign_renewal_payment',
            AssignRenewalPaymentToSubscriptionFormDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $form = $provider->provide(['form' => $form, 'subscription' => $subscription]);
        }

        $form = $this->addRenewalPaymentComponents($form, $subscription);

        $form->setDefaults([
            'subscription_id' => $subscription->id,
        ]);

        $form->onValidate[] = [$this, 'onValidate'];
        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    private function addRenewalPaymentComponents(Form $form, $subscription)
    {
        $form->addGroup()->setOption('container', Html::el('span id="renewal_payment_id"'));
        $assignedRenewalPayment = $this->renewalPayment->getRenewalPayment($subscription);
        $paymentOptions = $this->paymentsRepository->getTable()
            ->whereOr([
                'status = ? AND user_id = ?' => [PaymentStatusEnum::Form->value, $subscription->user_id],
                'id' => $assignedRenewalPayment?->id,
            ])
            ->order('created_at DESC');

        $disableOptions = $this->subscriptionMetaRepository->getTable()
            ->where([
                'key' => RenewalPayment::RENEWAL_PAYMENT_META_KEY,
                'value' => (clone $paymentOptions)->select('id'),
                'subscription_id != ?' => $subscription->id,
            ])
            ->fetchPairs(null, 'value');

        $renewalPaymentSelect = $form->addSelect(
            'renewal_payment_id',
            'payments.admin.component.assign_renewal_payment_subscription_form.renewal_payment.label',
            $this->preparePaymentOptions($paymentOptions),
        );
        $renewalPaymentSelect
            ->setDisabled($disableOptions)
            ->setDefaultValue($assignedRenewalPayment?->id)
            ->setPrompt('--')
            ->setHtmlAttribute('class', 'form-control')
            ->getControlPrototype()
            ->addAttributes([
                'class' => 'select2',
                'id' => 'renewal_payment_id',
            ]);
        $renewalPaymentSelect
            ->addConditionOn($form['mode'], Form::Equal, self::PAYMENT_MODE)
            ->setRequired('payments.admin.component.assign_renewal_payment_subscription_form.renewal_payment.required')
            ->endCondition();

        $form->addGroup();
        $form->addSubmit('submit', 'payments.admin.component.assign_renewal_payment_subscription_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-primary');

        $form->addSubmit('reset', 'payments.admin.component.assign_renewal_payment_subscription_form.reset')
            ->setValidationScope([$form['subscription_id']])
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-default');

        return $form;
    }

    public function onValidate(Form $form, $data)
    {
        $renewalPaymentId = $form->getComponent('renewal_payment_id')?->getRawValue();
        if (!$renewalPaymentId) {
            return;
        }

        $renewalPayment = $this->paymentsRepository->find($renewalPaymentId);
        $subscription = $this->subscriptionsRepository->find($data['subscription_id']);

        $assignedRenewalPayment = $this->renewalPayment->getRenewalPayment($subscription);
        if ($assignedRenewalPayment?->id === $renewalPaymentId) {
            return;
        }

        if ($renewalPayment->status !== PaymentStatusEnum::Form->value || $renewalPayment->user_id !== $subscription->user_id) {
            $form->addError($this->translator->translate(
                'payments.admin.component.assign_renewal_payment_subscription_form.error',
                ['payment_id' => $renewalPayment, 'subscription_id' => $data['subscription_id']],
            ));
        }
    }

    public function formSucceeded($form, $values)
    {
        $subscription = $this->subscriptionsRepository->find($values['subscription_id']);

        /** @var AssignRenewalPaymentToSubscriptionFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.assign_renewal_payment',
            AssignRenewalPaymentToSubscriptionFormDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $form = $provider->formSucceeded($form, $values);
        }

        if (!isset($values['mode']) || $values['mode'] !== self::PAYMENT_MODE) {
            $this->renewalPayment->unsetRenewalPayment($subscription);
        } else {
            $renewalPayment = $this->paymentsRepository->find($values['renewal_payment_id']);
            $this->renewalPayment->attachRenewalPayment($subscription, $renewalPayment);
        }

        if ($this->onSave) {
            $this->onSave->__invoke();
        }
    }

    private function preparePaymentOptions($paymentOptions): array
    {
        $result = [];
        foreach ($paymentOptions as $paymentOption) {
            $price = $this->priceHelper->getFormattedPrice($paymentOption->amount);

            $result[$paymentOption->id] = sprintf(
                "VS: %s - %s",
                $paymentOption->variable_symbol,
                $price,
            );
        }

        return $result;
    }
}
