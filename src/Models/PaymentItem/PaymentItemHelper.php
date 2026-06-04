<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

class PaymentItemHelper
{
    public static function getPriceWithoutVAT($unitPrice, $vat): float
    {
        // $unitPrice / (1 + ($vat / 100))
        return round(bcdiv($unitPrice, bcadd(1, bcdiv($vat, 100, 4), 4), 4), 2);
    }
}
