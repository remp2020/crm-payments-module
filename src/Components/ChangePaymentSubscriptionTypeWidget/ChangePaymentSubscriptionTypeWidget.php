<?php

namespace Crm\PaymentsModule\Components\ChangePaymentSubscriptionTypeWidget;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Forms\ChangePaymentSubscriptionTypeFormFactory;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Localization\Translator;

class ChangePaymentSubscriptionTypeWidget extends BaseLazyWidget
{
    public string $templateName = 'change_payment_subscription_type_widget.latte';

    private Translator $translator;

    private ActiveRow $payment;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        Translator $translator
    ) {
        parent::__construct($lazyWidgetManager);
        $this->translator = $translator;
    }

    public function header(): string
    {
        return 'Change subscription type';
    }

    public function identifier(): string
    {
        return 'changepaymentsubscriptiontypewidget';
    }

    public function render($payment)
    {
        if ($payment->status !== PaymentsRepository::STATUS_FORM || !$payment->subscription_type_id) {
            return false;
        }

        $this->payment = $payment;

        $this->template->payment = $payment;
        $this->template->link = $this->getPresenter()->link(
            ':Subscriptions:SubscriptionTypesAdmin:changeSubscriptionType',
            [
                'id' => $payment->id
            ]
        );
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function createComponentChangePaymentSubscriptionTypeForm(
        ChangePaymentSubscriptionTypeFormFactory $changeSubscriptionTypeFormFactory
    ): Form {
        if (isset($this->payment)) {
            $changeSubscriptionTypeFormFactory->setPayment($this->payment);
        }
        $form = $changeSubscriptionTypeFormFactory->create();
        $changeSubscriptionTypeFormFactory->onSave = function () {
            $this->getPresenter()->flashMessage(
                $this->translator->translate('payments.admin.component.change_payment_subscription_type_widget.success')
            );
            $this->getPresenter()->redirect('this');
        };
        return $form;
    }
}
