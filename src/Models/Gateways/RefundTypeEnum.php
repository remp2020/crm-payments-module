<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum RefundTypeEnum: string
{
    use EnumHelper;

    case Full = 'full';
    case Partial = 'partial';
}
