<?php

namespace Crm\PaymentsModule\Components;

interface DuplicateRecurrentPaymentsControlFactoryInterface
{
    public function create(): DuplicateRecurrentPayments;
}
