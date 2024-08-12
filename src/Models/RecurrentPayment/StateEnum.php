<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Models\RecurrentPayment;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum StateEnum: string
{
    use EnumHelper;

    case UserStop = 'user_stop';
    case AdminStop = 'admin_stop';
    case Active = 'active';
    case Pending = 'pending';
    case Charged = 'charged';
    case ChargeFailed = 'charge_failed';
    case SystemStop = 'system_stop';
}
