<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\VatRate;

enum VatMode: String
{
    case B2C = 'b2c';
    case B2B = 'b2b';
    case B2BNonEurope = 'b2b_non_europe';
    case B2BReverseCharge = 'b2b_reverse_charge';
}
