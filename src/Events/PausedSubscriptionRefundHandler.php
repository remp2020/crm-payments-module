<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Exception;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class PausedSubscriptionRefundHandler extends AbstractListener
{
    public function __construct(
        private readonly SubscriptionPauser $subscriptionPauser,
    ) {
    }

    public function handle(EventInterface $event): void
    {
        if (!$event instanceof PaymentChangeStatusEvent) {
            throw new Exception("Invalid type of event received, 'PaymentChangeStatusEvent' expected: " . get_class($event));
        }

        $payment = $event->getPayment();
        if ($payment->status !== PaymentStatusEnum::Refund->value || !$payment->subscription_id) {
            return;
        }

        $this->subscriptionPauser->stopResumedSubscriptions($payment->subscription);
    }
}
