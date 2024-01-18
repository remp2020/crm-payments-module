<?php

namespace Crm\PaymentsModule\Components\DuplicateRecurrentPayments;

interface DuplicateRecurrentPaymentsControlFactoryInterface
{
    public function create(): DuplicateRecurrentPayments;
}
