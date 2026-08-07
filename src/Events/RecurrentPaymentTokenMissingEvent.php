<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

/**
 * Emitted when a payment on a recurrent gateway was successfully completed,
 * but the gateway didn't return a recurrent token (e.g. the bank failed to
 * register the card), so no recurrent payment could be created and the
 * subscription won't renew automatically.
 */
class RecurrentPaymentTokenMissingEvent extends AbstractEvent implements PaymentEventInterface
{
    public function __construct(private ActiveRow $payment)
    {
    }

    public function getPayment(): ActiveRow
    {
        return $this->payment;
    }
}
