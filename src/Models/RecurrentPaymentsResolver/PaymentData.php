<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\RecurrentPaymentsResolver;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Nette\Database\Table\ActiveRow;

final class PaymentData
{
    public function __construct(
        public readonly PaymentItemContainer $paymentItemContainer,
        public readonly ActiveRow $subscriptionType,
        public readonly ?float $customChargeAmount,
        public readonly null|float|int $additionalAmount,
        public readonly ?string $additionalType,
        public readonly ?ActiveRow $address,
        public readonly ?ActiveRow $paymentCountry,
        public readonly ?string $paymentCountryResolutionReason,
    ) {
    }
}
