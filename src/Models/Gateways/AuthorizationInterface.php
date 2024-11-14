<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;

interface AuthorizationInterface
{
    public const PAYMENT_META_CARD_ID = 'card_id';
    public const PAYMENT_META_CARD_NUMBER = 'card_number';

    public function cancel(ActiveRow $payment): bool;

    public function getAuthorizationAmount(): float;
}
