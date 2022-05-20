<?php

namespace Crm\PaymentsModule\PaymentItem;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;

trait PaymentItemTrait
{
    private $name;

    private $price;

    private $vat;

    private $count;

    public function type(): string
    {
        return self::TYPE;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function unitPrice(): float
    {
        return $this->price;
    }

    public function totalPrice(): float
    {
        return $this->unitPrice() * $this->count();
    }

    public function vat(): int
    {
        return $this->vat;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function unitPriceWithoutVAT(): float
    {
        return PaymentItemHelper::getPriceWithoutVAT($this->unitPrice(), $this->vat());
    }

    public function totalPriceWithoutVAT(): float
    {
        return $this->unitPriceWithoutVAT() * $this->count();
    }
}
