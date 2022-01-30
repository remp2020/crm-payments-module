<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Localization\ITranslator;

/**
 * This widget allow to edit payment status in case status is other than `paid`.
 * Renders bootstrap with simple form and handles submit.
 *
 * @package Crm\PaymentsModule\Components
 */
class ChangePaymentStatus extends BaseWidget
{
    private $templateName = 'change_payment_status.latte';

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var ITranslator */
    private $translator;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository,
        ITranslator $translator
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = 'payment modal';
        return $header;
    }

    public function identifier()
    {
        return 'paymentmodal';
    }

    public function render($payment)
    {
        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleChangeStatusToPaidWithEmail($paymentId)
    {
        return $this->changeStatusToPaid($paymentId, true);
    }

    public function handleChangeStatusToPaidWithoutEmail($paymentId)
    {
        return $this->changeStatusToPaid($paymentId, false);
    }

    private function changeStatusToPaid($paymentId, $sendEmail)
    {
        $payment = $this->paymentsRepository->find($paymentId);
        if ($payment->status != PaymentsRepository::STATUS_PAID) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID, $sendEmail);

            $this->presenter->flashMessage($this->translator->translate('payments.admin.component.change_payment_status.messages.status_changed_successfully'));
            $this->presenter->redirect(':Users:UsersAdmin:Show', $payment->user_id);
        } else {
            $this->presenter->flashMessage($this->translator->translate('payments.admin.component.change_payment_status.messages.status_not_changed'));
            $this->presenter->redirect(':Users:UsersAdmin:Show', $payment->user_id);
        }

        return true;
    }
}
