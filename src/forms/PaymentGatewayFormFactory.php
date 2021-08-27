<?php

namespace Crm\PaymentsModule\Forms;

use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Nette\Application\UI\Form;
use Nette\Localization\ITranslator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class PaymentGatewayFormFactory
{
    protected $paymentGatewaysRepository;

    protected $translator;

    public $onSave;

    public $onUpdate;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        ITranslator $translator
    ) {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->translator = $translator;
    }

    /**
     * @param int $paymentGatewayId
     * @return Form
     */
    public function create($paymentGatewayId)
    {
        $paymentGateway = $this->paymentGatewaysRepository->find($paymentGatewayId);
        if (!$paymentGateway) {
            throw new \Exception('invalid paymentGatewayId provided: ' . $paymentGatewayId);
        }

        $defaults = $paymentGateway->toArray();

        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());
        $form->addProtection();

        $form->addText('name', 'payments.form.payment_gateway.name.label')
            ->setRequired('payments.form.payment_gateway.name.required')
            ->setHtmlAttribute('placeholder', 'payments.form.payment_gateway.name.placeholder');

        $form->addText('code', 'payments.form.payment_gateway.code.label')
            ->setHtmlAttribute('placeholder', 'payments.form.payment_gateway.code.placeholder')
            ->setDisabled();

        $form->addCheckbox('visible', 'payments.form.payment_gateway.visible.label');

        $form->addCheckbox('is_recurrent', 'payments.form.payment_gateway.is_recurrent.label')
            ->setDisabled();

        $form->addtext('sorting', 'payments.form.payment_gateway.sorting.label');

        $form->addSubmit('send', 'payments.form.payment_gateway.save.label')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> ' . $this->translator->translate('payments.form.payment_gateway.save.label'));

        if ($paymentGatewayId) {
            $form->addHidden('payment_gateway_id', $paymentGatewayId);
        }

        $form->setDefaults($defaults);

        $form->onSuccess[] = [$this, 'formSucceeded'];

        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $paymentGatewayId = $values['payment_gateway_id'];
        unset($values['payment_gateway_id']);

        $paymentGateway = $this->paymentGatewaysRepository->find($paymentGatewayId);
        $this->paymentGatewaysRepository->update($paymentGateway, $values);
        $this->onUpdate->__invoke($form, $paymentGateway);
    }
}
