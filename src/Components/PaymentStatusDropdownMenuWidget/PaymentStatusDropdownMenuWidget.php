<?php

namespace Crm\PaymentsModule\Components\PaymentStatusDropdownMenuWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

class PaymentStatusDropdownMenuWidget extends BaseLazyWidget
{
    private string $templateName = 'payment_status_dropdown_menu_widget.latte';

    public function identifier(): string
    {
        return 'paymentstatusdropdownmenuwidget';
    }

    public function render(ActiveRow $payment): void
    {
        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
