<?php

namespace Crm\PaymentsModule\Models\OneStopShop;

enum CountryResolutionType: string
{
    case IP_ADDRESS = 'ip_address';
    case INVOICE_ADDRESS = 'invoice_address';
    case PAYMENT_ADDRESS = 'payment_address';
    case USER_SELECTED = 'user_selected';
}
