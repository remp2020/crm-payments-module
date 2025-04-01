<?php

namespace Crm\PaymentsModule\Events;

use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class RecurrentPaymentCardExpiredEventHandler extends AbstractListener
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly Emitter $emitter,
        private readonly ActiveRowFactory $activeRowFactory
    ) {
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof RecurrentPaymentCardExpiredEvent)) {
            throw new \Exception('Invalid type of event received, RecurrentPaymentCardExpired expected: ' . get_class($event));
        }

        $recurrentPayment = $event->getRecurrentPayment();
        $hasChargeableRecurrent = $this->recurrentPaymentsRepository->all()->where([
                'user_id' => $recurrentPayment->user_id,
                'payment_method.external_token != ?' => $recurrentPayment->payment_method->external_token,
                'charge_at > ?' => $recurrentPayment->charge_at,
                'status' => RecurrentPaymentStateEnum::Active->value,
            ])->count('*') > 0;

        if ($hasChargeableRecurrent) {
            return;
        }
        if (!isset($recurrentPayment->user->email)) {
            return;
        }

        $userRow = $this->activeRowFactory->create([
            'email' => $recurrentPayment->user->email,
        ]);
        $this->emitter->emit(new NotificationEvent($this->emitter, $userRow, 'card_expires_this_month'));
    }
}
