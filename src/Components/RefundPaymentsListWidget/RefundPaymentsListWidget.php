<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class RefundPaymentsListWidget extends BaseWidget
{
    private $templateName = 'refund_payments_list_widget.latte';

    private $paymentsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($widgetManager);

        $this->paymentsRepository = $paymentsRepository;
    }

    public function identifier()
    {
        return 'refundpaymentslistwidget';
    }

    public function render($user)
    {
        $this->template->payments = $this->paymentsRepository->userRefundPayments($user->id);

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName);
        $this->template->render();
    }
}
