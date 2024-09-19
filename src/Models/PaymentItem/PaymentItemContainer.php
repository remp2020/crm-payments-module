<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

class PaymentItemContainer
{
    /** @var PaymentItemInterface[] */
    private array $items = [];

    private bool $preventOssVatChange = false;

    private bool $unreliable = false;

    private bool $zeroPriceAllowed = false;

    /**
     * Is true if container contains GenericPaymentItem, which means
     * some payment item type was not registered in PaymentItemContainerFactory
     * @return bool
     */
    public function isUnreliable(): bool
    {
        return $this->unreliable;
    }

    public function setUnreliable(bool $unreliable = true): void
    {
        $this->unreliable = $unreliable;
    }

    public function isZeroPriceAllowed(): bool
    {
        return $this->zeroPriceAllowed;
    }

    public function setZeroPriceAllowed(bool $zeroPriceAllowed = true): void
    {
        $this->zeroPriceAllowed = $zeroPriceAllowed;
    }

    public function addItem(PaymentItemInterface $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function addItems(array $items): self
    {
        foreach ($items as $item) {
            $this->addItem($item);
        }
        return $this;
    }

    /**
     * Switch $oldItem in PaymentItemContainer with $newItem
     *
     * @throws PaymentItemContainerException - Thrown if incorrect $itemIndex and $oldItem are provided.
     */
    public function switchItem(int $itemIndex, PaymentItemInterface $oldItem, PaymentItemInterface $newItem): self
    {
        if ($this->items[$itemIndex] === $oldItem) {
            $this->items[$itemIndex] = $newItem;
            return $this;
        }

        throw new PaymentItemContainerException("Unable to find PaymentItem [{$oldItem->name()}] with provided index [{$itemIndex}] in PaymentItemContainer. Container not updated.");
    }

    /**
     * @return PaymentItemInterface[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function totalPrice(): float
    {
        $price = 0;
        foreach ($this->items() as $item) {
            $price += $item->totalPrice();
        }
        return $price;
    }

    public function totalPriceWithoutVAT(): float
    {
        $priceWithoutVAT = 0;
        foreach ($this->items() as $item) {
            $priceWithoutVAT += $item->totalPriceWithoutVAT();
        }
        return $priceWithoutVAT;
    }

    public function getUnreliableWarning(): string
    {
        $unregisteredTypes = [];
        foreach ($this->items as $item) {
            if ($item instanceof GenericPaymentItem) {
                $unregisteredTypes[] = $item->type();
            }
        }
        return 'PaymentItemContainer is unreliable, since it contains unregistered PaymentItem types [' .
            implode(', ', $unregisteredTypes) . ']. This may cause unexpected problems when working with payments. ' .
            'Make sure you register custom types using PaymentItemContainerFactory#registerPaymentItemType';
    }

    public function preventOssVatChange(): bool
    {
        return $this->preventOssVatChange;
    }

    /**
     * If set to true, One Stop Shop (if enabled) will not adjust VAT rates on payment items in container.
     * This may be useful for B2B payments.
     * @param bool $value
     *
     * @return $this
     */
    public function setPreventOssVatChange(bool $value = true): self
    {
        $this->preventOssVatChange = $value;
        return $this;
    }
}
