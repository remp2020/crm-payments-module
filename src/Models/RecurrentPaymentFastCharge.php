<?php

namespace Crm\PaymentsModule\Models;

use Exception;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentFastCharge extends Exception
{

    public function __construct(ActiveRow $recurrentPayment)
    {
        $msg = 'RecurringPayment_id: ' . $recurrentPayment->id;
        $msg .= ' Card_id: ' . $recurrentPayment->cid;
        $msg .= ' User_id: ' . $recurrentPayment->user_id;
        $msg .= ' Error: Fast charge';
        parent::__construct($msg);
    }
}
