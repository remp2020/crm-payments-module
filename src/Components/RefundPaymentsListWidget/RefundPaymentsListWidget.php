<?php

namespace Crm\PaymentsModule\Components\RefundPaymentsListWidget;

use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repository\PaymentsRepository;

class RefundPaymentsListWidget extends BaseLazyWidget
{
    private $templateName = 'refund_payments_list_widget.latte';

    private $paymentsRepository;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        PaymentsRepository $paymentsRepository
    ) {
        parent::__construct($lazyWidgetManager);

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
