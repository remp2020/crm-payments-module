<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\ParsedMailLog;

use Crm\ApplicationModule\Helpers\EnumHelper;

enum State: string
{
    use EnumHelper;

    case WITHOUT_VS = 'without_vs';
    case ALREADY_PAID = 'already_paid';
    case DUPLICATED_PAYMENT = 'duplicated_payment';
    case CHANGED_TO_PAID = 'changed_to_paid';
    case PAYMENT_NOT_FOUND = 'payment_not_found';
    case DIFFERENT_AMOUNT = 'different_amount';
    case AUTO_NEW_PAYMENT = 'auto_new_payment';
    case NO_SIGN = 'no_sign';
    case NOT_VALID_SIGN = 'no_valid_sign';
    case ALREADY_REFUNDED = 'already_refunded';
}
