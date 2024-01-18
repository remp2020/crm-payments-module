<?php

namespace Crm\PaymentsModule\Components\ChangePaymentStatus;

interface ChangePaymentStatusFactoryInterface
{
    public function create(): ChangePaymentStatus;
}
