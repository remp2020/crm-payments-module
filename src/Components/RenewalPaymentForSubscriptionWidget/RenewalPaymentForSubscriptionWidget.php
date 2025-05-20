<?php

namespace Crm\PaymentsModule\Components\RenewalPaymentForSubscriptionWidget;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Forms\AssignRenewalPaymentToSubscriptionFormFactory;
use Nette\Application\UI\Multiplier;
use Nette\Database\Table\ActiveRow;

class RenewalPaymentForSubscriptionWidget extends BaseLazyWidget
{
    private string $templateName = 'renewal_payment_for_subscription_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private readonly Translator $translator,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier(): string
    {
        return 'renewalpaymentforsubscriptionwidget';
    }

    public function render(ActiveRow $subscription): void
    {
        if ($subscription->end_time < new \DateTime()) {
            return;
        }

        $this->template->subscription = $subscription;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }

    protected function createComponentAssignRenewalPaymentToSubscriptionForm(
        AssignRenewalPaymentToSubscriptionFormFactory $assignRenewalPaymentToSubscriptionFormFactory,
    ): Multiplier {
        return new Multiplier(function ($subscriptionId) use ($assignRenewalPaymentToSubscriptionFormFactory) {
            $form = $assignRenewalPaymentToSubscriptionFormFactory->create($subscriptionId);
            $assignRenewalPaymentToSubscriptionFormFactory->onSave = function () {
                $this->getPresenter()->flashMessage(
                    $this->translator->translate('payments.admin.component.renewal_payment_for_subscription_widget.success'),
                );
                $this->getPresenter()->redirect('this');
            };
            return $form;
        });
    }
}
