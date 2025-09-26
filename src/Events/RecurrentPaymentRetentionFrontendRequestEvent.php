<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentRetentionFrontendRequestEvent extends AbstractEvent implements RecurrentPaymentEventInterface
{
    public function __construct(
        private readonly ActiveRow $recurrentPayment,
    ) {
    }

    public function getRecurrentPayment(): ActiveRow
    {
        return $this->recurrentPayment;
    }
}
