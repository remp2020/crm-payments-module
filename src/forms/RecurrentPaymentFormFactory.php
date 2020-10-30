<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Subscription\SubscriptionTypeHelper;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RecurrentPaymentFormFactory
{
    private $recurrentPaymentsRepository;

    private $subscriptionTypesRepository;

    private $translator;

    private $subscriptionTypeHelper;

    public $onUpdate;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        ITranslator $translator,
        SubscriptionTypeHelper $subscriptionTypeHelper
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->translator = $translator;
        $this->subscriptionTypeHelper = $subscriptionTypeHelper;
    }

    public function create($recurrentPaymentId)
    {
        $recurrent = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        $defaults = $recurrent->toArray();

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addText('charge_at', 'payments.admin.component.recurrent_payment_form.charge_at.label')
            ->setRequired('payments.admin.component.recurrent_payment_form.charge_at.required')
            ->setAttribute('placeholder', 'payments.admin.component.recurrent_payment_form.charge_at.placeholder')
            ->setAttribute('class', 'flatpickr')
            ->setAttribute('flatpickr_datetime', "1");

        $form->addText('retries', 'payments.admin.component.recurrent_payment_form.retries.label')
            ->setRequired('payments.admin.component.recurrent_payment_form.retries.required')
            ->setType('number')
            ->setAttribute('placeholder', 'payments.admin.component.recurrent_payment_form.retries.placeholder');

        $subscriptionTypePairs = $this->subscriptionTypeHelper->getPairs($this->subscriptionTypesRepository->getAllActive(), true);
        $nextSubscriptionType = $form->addSelect(
            'next_subscription_type_id',
            'payments.admin.component.recurrent_payment_form.next_subscription_type_id.label',
            $subscriptionTypePairs
        )->setPrompt('--')
            ->setOption('description', $this->translator->translate('payments.admin.component.recurrent_payment_form.next_subscription_type_id.description', ['actual' => $recurrent->subscription_type->name]));
        $nextSubscriptionType->getControlPrototype()->addAttributes(['class' => 'select2']);

        $form->addText(
            'custom_amount',
            'payments.admin.component.recurrent_payment_form.custom_amount.label'
        )
            ->setHtmlType('number')
            ->setHtmlAttribute('readonly', 'readonly')
            ->setOption('description', 'payments.admin.component.recurrent_payment_form.custom_amount.description');

        $form->addSelect('state', 'payments.admin.component.recurrent_payment_form.state.label', [
            RecurrentPaymentsRepository::STATE_USER_STOP => RecurrentPaymentsRepository::STATE_USER_STOP,
            RecurrentPaymentsRepository::STATE_ADMIN_STOP => RecurrentPaymentsRepository::STATE_ADMIN_STOP,
            RecurrentPaymentsRepository::STATE_ACTIVE => RecurrentPaymentsRepository::STATE_ACTIVE,
            RecurrentPaymentsRepository::STATE_CHARGED => RecurrentPaymentsRepository::STATE_CHARGED,
            RecurrentPaymentsRepository::STATE_CHARGE_FAILED => RecurrentPaymentsRepository::STATE_CHARGE_FAILED,
            RecurrentPaymentsRepository::STATE_SYSTEM_STOP => RecurrentPaymentsRepository::STATE_SYSTEM_STOP,
        ])->setPrompt('payments.admin.component.recurrent_payment_form.state.prompt')
            ->setRequired('payments.admin.component.recurrent_payment_form.state.required')
            ->setAttribute('placeholder', 'payments.admin.component.recurrent_payment_form.state.placeholder');

        $form->addText('note', 'payments.admin.component.recurrent_payment_form.note.label')
            ->setAttribute('placeholder', '');

        $form->addHidden('id', $recurrentPaymentId);

        $form->addSubmit('send', 'payments.admin.component.recurrent_payment_form.save')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('payments.admin.component.recurrent_payment_form.save'));

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $recurentPayment = $this->recurrentPaymentsRepository->find($values->id);

        if ($values->note == '') {
            $values->note = null;
        }

        $values->charge_at = DateTime::from(strtotime($values->charge_at));

        unset($values['id']);
        if ($values['custom_amount'] == 0) {
            $values['custom_amount'] = null;
        }

        $this->recurrentPaymentsRepository->update($recurentPayment, $values);
        $this->onUpdate->__invoke($form, $recurentPayment);
    }
}
