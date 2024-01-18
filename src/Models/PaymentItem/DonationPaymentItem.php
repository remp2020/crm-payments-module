<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

class DonationPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    const TYPE = 'donation';

    public function __construct(string $name, float $price, int $vat)
    {
        $this->name = $name;
        $this->price = $price;
        $this->vat = $vat;
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
