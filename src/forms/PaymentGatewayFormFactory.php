<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapRenderer;

class PaymentGatewayFormFactory
{
    /** @var PaymentGatewaysRepository */
    protected $paymentGatewaysRepository;

    public $onSave;

    public $onUpdate;

    public function __construct(PaymentGatewaysRepository $paymentGatewaysRepository)
    {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
    }

    /**
     * @param int $paymentGatewayId
     * @return Form
     */
    public function create($paymentGatewayId)
    {
        $defaults = [];
        if (isset($paymentGatewayId)) {
            $paymentGateway = $this->paymentGatewaysRepository->find($paymentGatewayId);
            $defaults = $paymentGateway->toArray();
        }

        $form = new Form;

        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addText('name', 'Meno')
            ->setRequired('Meno musí byť zadané')
            ->setAttribute('placeholder', 'napríklad Tatrapay');

        $form->addText('code', 'Identifikátor')
            ->setAttribute('placeholder', 'Napríklad tatrapay');

        $form->addTextArea('description', 'Popis');

        $form->addCheckbox('active', 'Aktívny');
        $form->addCheckbox('visible', 'Viditeľný v objednávkovom procese');
        $form->addCheckbox('shop', 'Viditeľný v obchode');

        $form->addCheckbox('is_recurrent', 'Recurrená');

        $form->addtext('sorting', 'Poradie');

        $form->addSubmit('send', 'Ulož')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> Ulož');

        if ($paymentGatewayId) {
            $form->addHidden('payment_gateway_id', $paymentGatewayId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        if (isset($values['payment_gateway_id'])) {
            $paymentGatewayId = $values['payment_gateway_id'];
            unset($values['payment_gateway_id']);

            $paymentGateway = $this->paymentGatewaysRepository->find($paymentGatewayId);
            $this->paymentGatewaysRepository->update($paymentGateway, $values);
            $this->onUpdate->__invoke($form, $paymentGateway);
        } else {
            $paymentGateway = $this->paymentGatewaysRepository->add(
                $values['name'],
                $values['code'],
                $values['sorting'],
                $values['active'],
                $values['visible'],
                $values['shop'],
                $values['description'],
                $values['image'],
                $values['default'],
                $values['is_recurrent']
            );

            $this->onSave->__invoke($form, $paymentGateway);
        }
    }
}
