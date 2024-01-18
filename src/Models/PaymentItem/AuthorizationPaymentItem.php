<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

class AuthorizationPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'authorization';

    public function __construct(string $name, float $price)
    {
        $this->name = $name;
        $this->price = $price;
        $this->vat = 0;
        $this->count = 1;
    }

    public function data(): array
    {
        return [];
    }

    public function meta(): array
    {
        return [];
    }
}
