<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Nette\Database\Table\ActiveRow;

class DonationPaymentItemsListWidget extends BaseWidget
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
