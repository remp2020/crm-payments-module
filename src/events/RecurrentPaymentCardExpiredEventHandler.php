<?php

namespace Crm\PaymentsModule\Events;

use Crm\ApplicationModule\DataRow;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\UsersModule\Events\NotificationEvent;
use League\Event\AbstractListener;
use League\Event\Emitter;
use League\Event\EventInterface;

class RecurrentPaymentCardExpiredEventHandler extends AbstractListener
{
    private $recurrentPaymentsRepository;

    private $emitter;

    public function __construct(
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Emitter $emitter
    ) {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->emitter = $emitter;
    }

    public function handle(EventInterface $event)
    {
        if (!($event instanceof RecurrentPaymentCardExpiredEvent)) {
            throw new \Exception('Invalid type of event received, RecurrentPaymentCardExpired expected: ' . get_class($event));
        }

        $recurrentPayment = $event->getRecurrentPayment();
        $hasChargeableRecurrent = $this->recurrentPaymentsRepository->all()->where([
                'user_id' => $recurrentPayment->user_id,
                'cid != ?' => $recurrentPayment->cid,
                'charge_at > ?' => $recurrentPayment->charge_at,
                'status' => RecurrentPaymentsRepository::STATE_ACTIVE,
            ])->count('*') > 0;

        if ($hasChargeableRecurrent) {
            return;
        }
        if (!isset($recurrentPayment->user->email)) {
            return;
        }

        $userRow = new DataRow([
            'email' => $recurrentPayment->user->email,
        ]);
        $this->emitter->emit(new NotificationEvent($this->emitter, $userRow, 'card_expires_this_month'));
    }
}
