<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum RefundStatusEnum: string
{
    use EnumHelper;

    case Success = 'success';
    case Failure = 'failure';
}
