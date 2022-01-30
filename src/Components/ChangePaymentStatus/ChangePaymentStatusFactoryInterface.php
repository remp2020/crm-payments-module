<?php

namespace Crm\PaymentsModule\Components;

interface ChangePaymentStatusFactoryInterface
{
    public function create(): ChangePaymentStatus;
}
