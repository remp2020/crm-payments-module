<?php

namespace Crm\PaymentsModule\Components\SubscriptionTransferSummaryWidget;

use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;

class SubscriptionTransferSummaryWidget extends BaseLazyWidget
{
    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function identifier(): string
    {
        return 'subscriptiontransfersummarywidgetpayments';
    }

    public function render(array $params): void
    {
        if (!isset($params['subscription'])) {
            throw new Exception("Missing required param 'subscription'.");
        }

        $subscription = $params['subscription'];

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment === null) {
            return;
        }
        
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        $this->template->recurrentPayment = $recurrentPayment;

        $this->template->setFile(__DIR__ . DIRECTORY_SEPARATOR . 'subscription_transfer_summary_widget.latte');
        $this->template->render();
    }
}
