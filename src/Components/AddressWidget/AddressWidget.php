<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Nette\Database\Table\ActiveRow;

class AddressWidget extends BaseLazyWidget
{
    private $templateName = 'address_widget.latte';

    public function identifier()
    {
        return 'paymentaddresswidget';
    }

    public function render(ActiveRow $payment = null)
    {
        $this->template->payment = $payment;
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
