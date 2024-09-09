<?php

namespace Crm\PaymentsModule\Models\PaymentItem;

use Nette\Database\Table\ActiveRow;

final class PaymentItemContainerFactory
{
    private array $registeredPaymentItemClasses = [];

    public function createFromPayment(
        ActiveRow $payment,
        ?array $includedPaymentItemTypes = [],
        ?array $excludedPaymentItemTypes = []
    ): PaymentItemContainer {
        return $this->create($payment, new PaymentItemContainer(), $includedPaymentItemTypes, $excludedPaymentItemTypes);
    }

    public function addItemsFromPayment(
        ActiveRow $payment,
        PaymentItemContainer $container,
        ?array $includedPaymentItemTypes = [],
        ?array $excludedPaymentItemTypes = [],
    ): PaymentItemContainer {
        return $this->create($payment, $container, $includedPaymentItemTypes, $excludedPaymentItemTypes);
    }

    public function addItemFromPaymentItem(
        ActiveRow $paymentItem,
        PaymentItemContainer $container,
    ): void {
        if (isset($this->registeredPaymentItemClasses[$paymentItem->type])) {
            $item = $this->registeredPaymentItemClasses[$paymentItem->type]::fromPaymentItem($paymentItem);
        } else {
            $item = GenericPaymentItem::fromPaymentItem($paymentItem);
            $container->setUnreliable();
        }
        $container->addItem($item);
    }
    
    private function create(
        ActiveRow $payment,
        PaymentItemContainer $container,
        ?array $includedPaymentItemTypes = [],
        ?array $excludedPaymentItemTypes = [],
    ): PaymentItemContainer {
        $q = $payment->related('payment_items');
        if ($includedPaymentItemTypes) {
            $q = $q->where('type IN (?)', $includedPaymentItemTypes);
        }
        if ($excludedPaymentItemTypes) {
            $q = $q->where('type NOT IN (?)', $excludedPaymentItemTypes);
        }
        foreach ($q as $paymentItem) {
            $this->addItemFromPaymentItem($paymentItem, $container);
        }

        return $container;
    }

    public function registerPaymentItemType(string $class): void
    {
        if (!is_subclass_of($class, PaymentItemInterface::class)) {
            throw new \RuntimeException("Unable to register, class [$class] has to implement PaymentItemInterface");
        }
        if ($class::TYPE === null) {
            throw new \RuntimeException("Unable to register, class [$class] has 'null' type");
        }

        $this->registeredPaymentItemClasses[$class::TYPE] = $class;
    }
}
