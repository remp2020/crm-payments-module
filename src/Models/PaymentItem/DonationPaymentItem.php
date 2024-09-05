<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

final class DonationPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'donation';

    public function __construct(
        string $name,
        float $price,
        float $vat,
        array $meta = []
    ) {
        $this->name = $name;
        $this->price = $price;
        $this->vat = $vat;
        $this->count = 1;
        $this->meta = $meta;
    }

    public static function fromPaymentItem(ActiveRow $paymentItem): static
    {
        if ($paymentItem->type !== self::TYPE) {
            throw new \RuntimeException("Invalid type of payment item [{$paymentItem->type}], must be [" . self::TYPE . "]");
        }
        return new DonationPaymentItem(
            $paymentItem->name,
            $paymentItem->amount,
            $paymentItem->vat,
            self::loadMeta($paymentItem),
        );
    }

    public function data(): array
    {
        return [];
    }
}
