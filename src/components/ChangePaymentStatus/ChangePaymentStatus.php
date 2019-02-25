<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use League\Event\Emitter;

class ChangePaymentStatus extends BaseWidget
{
    private $templateName = 'change_payment_status.latte';

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var Emitter */
    private $emitter;

    public function __construct(WidgetManager $widgetManager, PaymentsRepository $paymentsRepository, Emitter $emitter)
    {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->emitter = $emitter;
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

            $this->presenter->flashMessage('Stav platby bol zmenený');
            $this->presenter->redirect(':Users:UsersAdmin:Show', $payment->user_id);
        } else {
            $this->presenter->flashMessage('Stav platby nebol zmenený');
            $this->presenter->redirect(':Users:UsersAdmin:Show', $payment->user_id);
        }

        return true;
    }
}
