<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SubscriptionsModule\Events\SubscriptionPreUpdateEvent;
use League\Event\AbstractListener;
use League\Event\EventInterface;
use Nette\Localization\Translator;

class SubscriptionPreUpdateHandler extends AbstractListener
{
    private $paymentsRepository;

    private $translator;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        Translator $translator,
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->translator = $translator;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof SubscriptionPreUpdateEvent)) {
            throw new \Exception('invalid type of event received: ' . get_class($event));
        }

        $subscription = $event->getSubscription();

        $payment = $this->paymentsRepository->subscriptionPayment($subscription);
        if ($payment && $payment->status == PaymentStatusEnum::Paid->value) {
            if ($payment->paid_at > $event->getValues()->start_time) {
                $event->getForm()['start_time']->addError($this->translator->translate('subscriptions.data.subscriptions.errors.start_time_before_paid_at', ['paid_at' => $payment->paid_at]));
                return;
            }
        }
    }
}
