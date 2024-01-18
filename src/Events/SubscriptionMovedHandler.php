<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Events\SubscriptionMovedEvent;
use DateTime;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class SubscriptionMovedHandler extends AbstractListener
{
    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function handle(EventInterface $event)
    {
        if (!$event instanceof SubscriptionMovedEvent) {
            throw new \Exception('Invalid type of event received, SubscriptionMovedEvent expected: ' . get_class($event));
        }

        $newEndTime = $event->getSubscription()->end_time;
        $originalEndTime = $event->getOriginalEndTime();

        $payment = $this->paymentsRepository->subscriptionPayment($event->getSubscription());
        if (!$payment) {
            return;
        }
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        if (!$recurrentPayment || $recurrentPayment->state !== RecurrentPaymentsRepository::STATE_ACTIVE) {
            return;
        }

        $diff = $originalEndTime->diff($newEndTime);

        /** @var DateTime $originalChargeAt */
        $originalChargeAt = $recurrentPayment->charge_at;

        $this->recurrentPaymentsRepository->update($recurrentPayment, [
            'charge_at' => $originalChargeAt->add($diff),
        ]);
    }
}
