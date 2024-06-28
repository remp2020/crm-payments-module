<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;

class StopRecurrentPaymentEvent extends AbstractEvent
{
    public function __construct(
        private int $recurrentPaymentId,
    ) {
    }

    public function getRecurrentPaymentId(): int
    {
        return $this->recurrentPaymentId;
    }
}
