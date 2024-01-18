<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\ActiveRow;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionTypeHelper;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Application\UI\Form;
use Nette\Database\Explorer;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ChangePaymentSubscriptionTypeFormFactory
{
    public $onSave;

    private ?ActiveRow $payment = null;

    public function __construct(
        private Translator $translator,
        private SubscriptionTypesRepository $subscriptionTypesRepository,
        private SubscriptionTypeHelper $subscriptionTypeHelper,
        private PaymentsRepository $paymentsRepository,
        private PaymentItemsRepository $paymentItemsRepository,
        private Explorer $database
    ) {
    }

    public function setPayment(ActiveRow $payment): void
    {
        $this->payment = $payment;
    }

    public function create(): Form
    {
        $form = new Form();
        $form->addProtection();
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        $subscriptionTypeOptions = $this->subscriptionTypesRepository->getAllActive()->fetchAll();
        if (isset($this->payment->subscription_type)) {
            $subscriptionTypeOptions[] = $this->payment->subscription_type;
        }
        $subscriptionTypePairs = $this->subscriptionTypeHelper->getPairs($subscriptionTypeOptions, true);

        $form->addSelect(
            'subscription_type_id',
            'payments.admin.component.change_payment_subscription_type_widget.subscription_type',
            $subscriptionTypePairs
        )->setRequired()->setHtmlAttribute('class', 'form-control')
            ->getControlPrototype()
            ->addAttributes(['class' => 'select2']);

        $form->addHidden('payment_id')->setRequired();

        if (isset($this->payment)) {
            $form->setDefaults([
                'payment_id' => $this->payment->id,
                'subscription_type_id' => $this->payment->subscription_type_id
            ]);
        }

        $form->addSubmit('submit', 'payments.admin.component.change_payment_subscription_type_widget.button')
            ->getControlPrototype()
            ->setName('button')
            ->setAttribute('class', 'btn btn-primary');

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $paymentId = $values['payment_id'];
        $payment = $this->paymentsRepository->find($paymentId);

        $newSubscriptionTypeId = $values['subscription_type_id'];
        $newSubscriptionType = $this->subscriptionTypesRepository->find($newSubscriptionTypeId);

        $this->database->beginTransaction();

        $oldSubscriptionTypeId = $payment->subscription_type_id;
        $paymentItemsToDelete = $this->paymentItemsRepository->getTable()
            ->where('payment_id', $payment->id)
            ->where('subscription_type_id', $oldSubscriptionTypeId)
            ->fetchAll();
        foreach ($paymentItemsToDelete as $paymentItemToDelete) {
            $this->paymentItemsRepository->deletePaymentItem($paymentItemToDelete);
        }

        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($newSubscriptionType));
        $this->paymentItemsRepository->add($payment, $paymentItemContainer);

        $paymentItems = $this->paymentItemsRepository->getByPayment($payment);
        $amount = 0;
        foreach ($paymentItems as $paymentItem) {
            $amount += $paymentItem->count * $paymentItem->amount;
        }

        $this->paymentsRepository->update($payment, [
            'subscription_type_id' => $newSubscriptionType->id,
            'amount' => $amount,
        ]);

        $this->database->commit();
        if ($this->onSave) {
            $this->onSave->__invoke();
        }
    }
}
