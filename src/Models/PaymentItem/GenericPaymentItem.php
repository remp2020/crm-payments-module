<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

final class GenericPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public function __construct(private readonly ActiveRow $paymentItem)
    {
        $this->vat = $paymentItem->vat;
        $this->count = $paymentItem->count;
        $this->price = $paymentItem->amount;
        $this->name = $paymentItem->name;
        $this->meta = self::loadMeta($this->paymentItem);
    }
    public function type(): string
    {
        return $this->paymentItem->type;
    }

    public static function fromPaymentItem(ActiveRow $paymentItem): static
    {
        return new GenericPaymentItem($paymentItem);
    }

    public function data(): array
    {
        return [
            ...$this->paymentItem->toArray(),
            'vat' => $this->vat, // only item that can be overridden
        ];
    }
}
