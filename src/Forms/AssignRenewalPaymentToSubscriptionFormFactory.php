<?php

namespace Crm\PaymentsModule\Forms;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Helpers\PriceHelper;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\Payment\RenewalPayment;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Tomaj\Form\Renderer\BootstrapRenderer;

class AssignRenewalPaymentToSubscriptionFormFactory
{
    public $onSave;

    public function __construct(
        private readonly Translator $translator,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
        private readonly PriceHelper $priceHelper,
        private readonly RenewalPayment $renewalPayment,
    ) {
    }

    public function create(string $subscriptionId): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        $assignedRenewalPayment = $this->renewalPayment->getRenewalPayment($subscription);

        $paymentOptions = $this->paymentsRepository->getTable()
            ->whereOr([
                'status = ? AND user_id = ?' => [PaymentStatusEnum::Form->value, $subscription->user_id],
                'id' => $assignedRenewalPayment?->id
            ])
            ->order('created_at DESC');

        $disableOptions = $this->subscriptionMetaRepository->getTable()
            ->where([
                'key' => RenewalPayment::RENEWAL_PAYMENT_META_KEY,
                'value' => (clone $paymentOptions)->select('id'),
                'subscription_id != ?' => $subscriptionId,
            ])
            ->fetchPairs(null, 'value');

        $form->addSelect(
            'renewal_payment_id',
            'payments.admin.component.assign_renewal_payment_subscription_form.renewal_payment',
            $this->preparePaymentOptions($paymentOptions)
        )
            ->setDisabled($disableOptions)
            ->setPrompt('--')
            ->setHtmlAttribute('class', 'form-control')
            ->getControlPrototype()
            ->addAttributes(['class' => 'select2']);

        $form->addHidden('subscription_id')->setRequired();

        $form->addSubmit('submit', 'payments.admin.component.assign_renewal_payment_subscription_form.submit')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-primary');

        $form->setDefaults([
            'subscription_id' => $subscription->id,
            'renewal_payment_id' => $assignedRenewalPayment?->id,
        ]);

        $form->onValidate[] = [$this, 'onValidate'];
        $form->onSuccess[] = [$this, 'formSucceeded'];
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
                ['payment_id' => $renewalPayment, 'subscription_id' => $data['subscription_id']]
            ));
        }
    }

    public function formSucceeded($form, $values)
    {
        $subscription = $this->subscriptionsRepository->find($values['subscription_id']);
        $renewalPaymentId = $form->getComponent('renewal_payment_id')?->getRawValue();

        if ($renewalPaymentId) {
            $this->renewalPayment->setRenewalPaymentId($subscription, $renewalPaymentId);
        } else {
            $this->renewalPayment->unsetRenewalPayment($subscription);
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
