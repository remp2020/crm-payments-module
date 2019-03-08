<?php

namespace Crm\PaymentsModule\PaymentItem;

class PaymentItemContainer
{
    private $items = [];

    public function addItem(PaymentItemInterface $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function items(): array
    {
        return $this->items;
    }
}