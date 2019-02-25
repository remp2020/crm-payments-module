<?php

namespace Crm\PaymentsModule\Components;

interface DuplicateRecurrentPaymentsControlFactoryInterface
{
    /** @return DuplicateRecurrentPayments */
    public function create();
}
