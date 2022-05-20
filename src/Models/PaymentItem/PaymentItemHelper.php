<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

class PaymentItemHelper
{
    public static function getPriceWithoutVAT($unitPrice, $vat): float
    {
        return round($unitPrice / (1 + ($vat / 100)), 2);
    }
}
