<?php

namespace Crm\PaymentsModule\PaymentItem;

interface PaymentItemInterface
{
    public function type(): string;

    public function name(): string;

    public function price(): float;

    public function vat(): int;

    public function count(): int;

    public function data(): array;
}
