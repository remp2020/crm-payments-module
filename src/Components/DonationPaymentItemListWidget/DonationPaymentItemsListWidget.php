<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Nette\Database\Table\ActiveRow;

/**
 * This widget takes payment item and renders result name, amount and price
 * in case it's donation payment item type.
 *
 * @package Crm\PaymentsModule\Components
 */
class DonationPaymentItemsListWidget extends BaseLazyWidget
{
    private $templateName = 'donation_payment_items_list_widget.latte';

    public function identifier()
    {
        return 'donationpaymentitemslistwidget';
    }

    public function render(ActiveRow $paymentItem)
    {
        if ($paymentItem->type !== DonationPaymentItem::TYPE) {
            return;
        }

        $this->template->paymentItem = $paymentItem;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
