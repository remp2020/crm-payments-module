<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\PaymentsModule\PaymentItem\AuthorizationPaymentItem;
use Nette\Database\Table\ActiveRow;

class AuthorizationPaymentItemListWidget extends BaseWidget
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
