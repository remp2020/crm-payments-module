<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class PaymentCopiedEvent extends AbstractEvent implements PaymentEventInterface
{
    public function __construct(
        private readonly ActiveRow $payment,
        private readonly ActiveRow $newPayment,
    ) {
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }

    public function getNewPayment(): ActiveRow
    {
        return $this->newPayment;
    }
}
