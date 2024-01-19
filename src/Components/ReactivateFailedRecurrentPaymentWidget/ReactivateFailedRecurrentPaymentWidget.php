<?php

namespace Crm\PaymentsModule\Components\ReactivateFailedRecurrentPaymentWidget;

use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;

class ReactivateFailedRecurrentPaymentWidget extends BaseLazyWidget
{
    private $templateName = 'reactivate_failed_recurrent_payment_widget.latte';

    public function __construct(
        LazyWidgetManager $widgetManager,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
        parent::__construct($widgetManager);
    }

    public function identifier()
    {
        return 'reactivatefailedrecurrentpaymentwidget';
    }

    public function render(ActiveRow $recurrentPayment)
    {
        if (!$this->recurrentPaymentsRepository->canBeReactivatedAfterSystemStopped($recurrentPayment)) {
            return;
        }

        $this->template->recurrentPayment = $recurrentPayment;

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
