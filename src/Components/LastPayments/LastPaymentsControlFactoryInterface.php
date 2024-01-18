<?php

namespace Crm\PaymentsModule\Components\LastPayments;

interface LastPaymentsControlFactoryInterface
{
    public function create(): LastPayments;
}
