<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

class PaymentDonationLabelWidget extends BaseLazyWidget
{
    private $templateName = 'payment_donation_label_widget.latte';

    public function identifier()
    {
        return 'payment_donation_label_widget';
    }

    public function render(ActiveRow $payment)
    {
        if (!$payment->additional_type || $payment->additional_amount <= 0) {
            return;
        }

        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
