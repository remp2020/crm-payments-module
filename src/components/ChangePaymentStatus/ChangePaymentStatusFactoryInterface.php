<?php

namespace Crm\PaymentsModule\Components;

interface ChangePaymentStatusFactoryInterface
{
    /** @return ChangePaymentStatus */
    public function create();
}
