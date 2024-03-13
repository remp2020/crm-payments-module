<?php

namespace Crm\PaymentsModule\Components\RefundPaymentItemsListWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;

class RefundPaymentItemsListWidget extends BaseLazyWidget
{
    private string $templateName = 'refund_payment_items_list_widget.latte';

    public function identifier(): string
    {
        return 'refundpaymentitemslistwidget';
    }

    public function render(array $params): void
    {
        /* @var Nette\Database\Table\ActiveRow $payment */
        $payment = $params['payment'];

        $this->template->paymentItems = $payment->related('payment_items');

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
