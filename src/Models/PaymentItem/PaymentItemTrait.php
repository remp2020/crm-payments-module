<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

trait PaymentItemTrait
{
    private string $name;

    private float $price;

    private float $vat;

    private int $count;

    private array $meta = [];

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

    public function vat(): float
    {
        return $this->vat;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function meta(): array
    {
        return $this->meta;
    }

    public function unitPriceWithoutVAT(): float
    {
        return PaymentItemHelper::getPriceWithoutVAT($this->unitPrice(), $this->vat());
    }

    public function totalPriceWithoutVAT(): float
    {
        return $this->unitPriceWithoutVAT() * $this->count();
    }

    public function forceVat(float $vat): static
    {
        $this->vat = $vat;
        return $this;
    }

    public static function loadMeta(ActiveRow $paymentItem): array
    {
        return $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value');
    }
}
