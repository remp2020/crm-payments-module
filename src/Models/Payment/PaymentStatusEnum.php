<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Payment;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum PaymentStatusEnum: string
{
    use EnumHelper;

    case Form = 'form';
    case Paid = 'paid';
    case Fail = 'fail';
    case Timeout = 'timeout';
    case Refund = 'refund';
    case Imported = 'imported';
    case Prepaid = 'prepaid';
    case Authorized = 'authorized';
}
