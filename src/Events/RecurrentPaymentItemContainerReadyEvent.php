<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

final class RecurrentPaymentItemContainerReadyEvent extends AbstractEvent implements RecurrentPaymentEventInterface
{
    public function __construct(
        private readonly PaymentItemContainer $paymentItemContainer,
        private readonly ActiveRow $recurrentPayment,
    ) {
    }

    public function getPaymentItemContainer(): PaymentItemContainer
    {
        return $this->paymentItemContainer;
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }
}
