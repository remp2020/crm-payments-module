<?php

namespace Crm\PaymentsModule\Components\AuthorizationPaymentItemListWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\PaymentsModule\Models\PaymentItem\AuthorizationPaymentItem;
use Nette\Database\Table\ActiveRow;

class AuthorizationPaymentItemListWidget extends BaseLazyWidget
{
    private $templateName = 'authorization_payment_items_list_widget.latte';

    public function identifier()
    {
        return 'authorizationpaymentitemslistwidget';
    }

    public function render(ActiveRow $paymentItem)
    {
        if ($paymentItem->type !== AuthorizationPaymentItem::TYPE) {
            return;
        }

        $this->template->paymentItem = $paymentItem;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
