<?php

namespace Crm\PaymentsModule\Components\PaymentItemsListWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Nette\Database\Table\ActiveRow;

/**
 * This widget takes payment item and renders name, count, amount and price
 * in case it's other type than `subscription_type`.
 *
 * @package Crm\PaymentsModule\Components
 */
class PaymentItemsListWidget extends BaseLazyWidget
{
    private $templateName = 'payment_items_list_widget.latte';

    public function identifier()
    {
        return 'paymentitemslistwidget';
    }

    public function render(ActiveRow $paymentItem)
    {
        if ($paymentItem->type !== SubscriptionTypePaymentItem::TYPE) {
            return;
        }

        $this->template->paymentItem = $paymentItem;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
