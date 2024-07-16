<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

final class AuthorizationPaymentItem implements PaymentItemInterface
{
    use PaymentItemTrait;

    public const TYPE = 'authorization';

    public function __construct(string $name, float $price, array $meta = [])
    {
        $this->name = $name;
        $this->price = $price;
        $this->meta = $meta;
        $this->vat = 0;
        $this->count = 1;
    }

    public static function fromPaymentItem(ActiveRow $paymentItem): static
    {
        if ($paymentItem->type !== self::TYPE) {
            throw new \RuntimeException("Invalid type of payment item [{$paymentItem->type}], must be [" . self::TYPE . "]");
        }
        return new AuthorizationPaymentItem(
            $paymentItem->name,
            $paymentItem->amount,
            self::loadMeta($paymentItem)
        );
    }

    public function data(): array
    {
        return [];
    }
}
