<?php

namespace Crm\PaymentsModule\Components\PaymentDetailPanelWidget;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Exception;

class PaymentDetailPanelWidget extends BaseLazyWidget
{
    private string $templateName = 'payment_detail_panel_widget.latte';

    public function identifier(): string
    {
        return 'paymentdetailpanelwidget';
    }

    public function render(array $params)
    {
        if (!isset($params['payment'])) {
            return;
        }

        if (!is_a($params['payment'], ActiveRow::class)) {
            throw new Exception(sprintf(
                "Param 'payment' must be type of '%s'.",
                ActiveRow::class,
            ));
        }

        $this->template->payment = $params['payment'];
        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
