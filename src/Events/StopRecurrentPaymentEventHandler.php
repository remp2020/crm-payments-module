<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;
use League\Event\AbstractListener;
use League\Event\EventInterface;

class StopRecurrentPaymentEventHandler extends AbstractListener
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
    }

    /**
     * @throws Exception
     */
    public function handle(EventInterface $event): void
    {
        if (!($event instanceof StopRecurrentPaymentEvent)) {
            throw new Exception('Invalid type of event `' . get_class($event) . '` received, expected: `StopRecurrentPaymentEvent`');
        }

        $recurrentPaymentId = $event->getRecurrentPaymentId();
        $recurrentPayment = $this->recurrentPaymentsRepository->stoppedBySystem($recurrentPaymentId);
        if (!$recurrentPayment) {
            throw new Exception("Recurrent payment ID: `{$recurrentPaymentId}` not found.");
        }
    }
}
