<?php

namespace Crm\PaymentsModule\Models\OneStopShop;

enum CountryResolutionTypeEnum: string
{
    case IpAddress = 'ip_address';
    case InvoiceAddress = 'invoice_address';
    case PaymentAddress = 'payment_address';
    case PrintAddress = 'print_address';
    case UserSelected = 'user_selected';
    case AdminSelected = 'admin_selected';
    case PreviousPayment = 'previous_payment';
    case DefaultCountry = 'default_country';
}
