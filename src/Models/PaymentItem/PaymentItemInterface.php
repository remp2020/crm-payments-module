<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

interface PaymentItemInterface
{
    public const TYPE = null; // override this

    public function type(): string;

    public function name(): string;

    public function unitPrice(): float;

    public function totalPrice(): float;

    public function vat(): int;

    public function count(): int;

    public function data(): array;

    public function unitPriceWithoutVAT(): float;

    public function totalPriceWithoutVAT(): float;

    public function meta(): array;

    public function forceVat(int $vat): static;

    public static function fromPaymentItem(ActiveRow $paymentItem): static;
}
