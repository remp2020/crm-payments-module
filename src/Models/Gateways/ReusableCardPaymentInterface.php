<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Gateways;

use Nette\Database\Table\ActiveRow;

interface ReusableCardPaymentInterface
{
    public function isCardReusable(ActiveRow $recurrentPayment): bool;
}
