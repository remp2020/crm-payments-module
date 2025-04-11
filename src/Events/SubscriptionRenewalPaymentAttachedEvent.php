<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionRenewalPaymentAttachedEvent extends AbstractEvent
{
    public function __construct(
        public readonly ActiveRow $renewalPayment,
        public readonly ActiveRow $subscription,
    ) {
    }
}
