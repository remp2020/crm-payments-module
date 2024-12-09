<?php

namespace Crm\PaymentsModule\Components\ChangePaymentCountryButtonWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Nette\Database\Table\ActiveRow;

class ChangePaymentCountryButtonWidget extends BaseLazyWidget
{
    public function __construct(
        private readonly OneStopShop $oneStopShop,
    ) {
    }

    public function render(ActiveRow $payment): void
    {
        $this->template->paymentId = $payment->id;

        if (!$this->oneStopShop->isEnabled()) {
            return;
        }

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . 'change_payment_country_button.latte');
        $this->template->render();
    }
}
