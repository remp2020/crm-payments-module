<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Utils\DateTime;
use Tomaj\Form\Renderer\BootstrapRenderer;

class RecurrentPaymentFormFactory
{
    private $recurrentPaymentsRepository;

    private $subscriptionTypesRepository;

    public $onUpdate;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
    }

    public function create($recurrentPaymentId)
    {
        $recurrent = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        $defaults = $recurrent->toArray();

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addText('charge_at', 'Dátum stiahnutia peňazí')
            ->setRequired('Dátum stiahnutia peňazí je povinný')
            ->setAttribute('placeholder', 'napríklad 4.3.2017 15:30');

        $form->addText('retries', 'Počet opakovaní pre neúspešnom stiahnutí peňazí')
            ->setRequired('Počet opakovaní je povinný')
            ->setType('number')
            ->setAttribute('placeholder', 'napríklad 4');

        $form->addSelect(
            'next_subscription_type_id',
            'Nasledujúce predplatné pri stiahnutí:',
            $this->subscriptionTypesRepository->all()->fetchPairs('id', 'name')
        )->setPrompt('--')
            ->setOption('description', 'Vybrať len v prípade že nasledujúce predplatné má byť iné ako aktuálne (' . $recurrent->subscription_type->name . ')');

        $form->addText(
            'custom_amount',
            'Suma pri nasledujúcom stiahnutí:'
        )
            ->setHtmlType('number')
            ->setHtmlAttribute('readonly', 'readonly')
            ->setOption('description', 'Suma bola pôvodne vypočítaná k upgradu. Pri zmene nasledujúceho predplatného bude odstránená, aby sa použila cena zo zvoleného predplatného.');

        $form->addSelect('state', 'Stav', [
            RecurrentPaymentsRepository::STATE_USER_STOP => RecurrentPaymentsRepository::STATE_USER_STOP,
            RecurrentPaymentsRepository::STATE_ADMIN_STOP => RecurrentPaymentsRepository::STATE_ADMIN_STOP,
            RecurrentPaymentsRepository::STATE_ACTIVE => RecurrentPaymentsRepository::STATE_ACTIVE,
            RecurrentPaymentsRepository::STATE_CHARGED => RecurrentPaymentsRepository::STATE_CHARGED,
            RecurrentPaymentsRepository::STATE_CHARGE_FAILED => RecurrentPaymentsRepository::STATE_CHARGE_FAILED,
            RecurrentPaymentsRepository::STATE_SYSTEM_STOP => RecurrentPaymentsRepository::STATE_SYSTEM_STOP,
            RecurrentPaymentsRepository::STATE_TB_FAILED => RecurrentPaymentsRepository::STATE_TB_FAILED,
        ])->setPrompt('Stav rekurentného profilu')
            ->setRequired('Počet opakovaní je povinný')
            ->setAttribute('placeholder', 'napríklad 4');

        $form->addText('note', 'Poznámka')
            ->setAttribute('placeholder', '');

        $form->addHidden('id', $recurrentPaymentId);

        $form->addSubmit('send', 'Ulož')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> Ulož');

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
