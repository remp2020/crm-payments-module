<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\ParsedMailLog;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum ParsedMailLogStateEnum: string
{
    use EnumHelper;

    case WithoutVs = 'without_vs';
    case AlreadyPaid = 'already_paid';
    case DuplicatedPayment = 'duplicated_payment';
    case ChangedToPaid = 'changed_to_paid';
    case PaymentNotFound = 'payment_not_found';
    case DifferentAmount = 'different_amount';
    case AutoNewPayment = 'auto_new_payment';
    case NoSign = 'no_sign';
    case NotValidSign = 'no_valid_sign';
    case AlreadyRefunded = 'already_refunded';
}
