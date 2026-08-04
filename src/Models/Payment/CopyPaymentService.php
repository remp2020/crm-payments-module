<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Payment;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainerFactory;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Nette\Database\Table\ActiveRow;

final readonly class CopyPaymentService
{
    public function __construct(
        private PaymentItemContainerFactory $paymentItemContainerFactory,
    ) {
    }

    /**
     * Copies SubscriptionTypePaymentItems of $payment into a new PaymentItemContainer.
     *
     * Payment items are the record of what the user actually paid for, so they are copied as-is
     * by default. The copy is only replaced with fresh items built from the current subscription
     * type when the copy is provably stale AND the replacement doesn't change the total price:
     *
     * - Total price (with VAT) unchanged, but total price without VAT differs - VAT rates changed
     *   since the original payment (e.g. law change absorbed in the consumer price). Copying would
     *   put outdated VAT on the new payment, so current subscription type items are used instead.
     * - Total price unchanged, but the number of items differs - subscription type item structure
     *   changed (e.g. one item was split into two). Current subscription type items are used.
     * - Total price (with VAT) changed (subscription type was repriced, payment was discounted,
     *   legacy data): the caller decides via $preserveCopiedItemsOnPriceChange, because the correct
     *   behavior depends on the use case:
     *   - true (copy of an existing payment, e.g. repeated bank transfer): keep the copied items,
     *     the user must be charged the same amount as agreed in the original payment.
     *   - false (upcoming recurrent charge): rebuild from the subscription type, the next charge
     *     follows the current catalog price.
     *
     * Other payment item types (donations, postal fees, ...) are not handled here; only items of
     * SubscriptionTypePaymentItem::TYPE are copied/compared.
     */
    public function copySubscriptionTypeItems(
        ActiveRow $payment,
        bool $preserveCopiedItemsOnPriceChange = true,
    ): PaymentItemContainer {
        if (!$payment->subscription_type) {
            // payment without associated subscription_type should not contain any subscription type items,
            // therefore returning empty container
            return new PaymentItemContainer();
        }

        $copiedContainer = $this->paymentItemContainerFactory->createFromPayment(
            $payment,
            [SubscriptionTypePaymentItem::TYPE],
        );

        $rebuiltContainer = new PaymentItemContainer();
        $rebuiltContainer->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($payment->subscription_type));

        $totalItemsCountEqual = count($copiedContainer->items()) === count($rebuiltContainer->items());
        $totalPricesWithoutVatEqual = round($rebuiltContainer->totalPriceWithoutVAT(), 2) === round($copiedContainer->totalPriceWithoutVAT(), 2);
        $totalPricesEqual = round($rebuiltContainer->totalPrice(), 2) === round($copiedContainer->totalPrice(), 2);

        if (!$totalPricesEqual) {
            // total price changed (repricing, discount, legacy data)
            return $preserveCopiedItemsOnPriceChange ? $copiedContainer : $rebuiltContainer;
        }

        if (!$totalItemsCountEqual || !$totalPricesWithoutVatEqual) {
            // same total, but VAT or item structure drifted - rebuild with current (correct) data
            return $rebuiltContainer;
        }
        return $copiedContainer;
    }
}
