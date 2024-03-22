<?php

namespace Crm\PaymentsModule\Events;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class BeforeCreateRenewalPaymentEvent extends AbstractEvent
{
    /**
     * Flag indicating whether creating a new payment should be prevented.
     *
     * @var bool
     */
    private bool $preventCreatingRenewalPayment = false;

    /**
     * Holds newly created payment (if created by event handler).
     *
     * @var ActiveRow|null
     */
    private ?ActiveRow $renewalPayment = null;

    public function __construct(
        private ActiveRow $endingSubscriptionType,
        private ActiveRow $paymentGateway,
        private ActiveRow $user,
        private PaymentItemContainer $paymentItemContainer,
        private array $newPaymentMetaData
    ) {
    }

    public function getEndingSubscriptionType(): ActiveRow
    {
        return $this->endingSubscriptionType;
    }

    public function getPaymentGateway(): ActiveRow
    {
        return $this->paymentGateway;
    }

    public function getUser(): ActiveRow
    {
        return $this->user;
    }

    public function getPaymentItemContainer(): PaymentItemContainer
    {
        return $this->paymentItemContainer;
    }

    public function getMetaData(): array
    {
        return $this->newPaymentMetaData;
    }

    public function shouldPreventCreatingRenewalPayment(): bool
    {
        return $this->preventCreatingRenewalPayment;
    }

    public function preventCreatingRenewalPayment(): void
    {
        $this->preventCreatingRenewalPayment = true;
    }

    public function getRenewalPayment(): ?ActiveRow
    {
        return $this->renewalPayment;
    }

    public function setRenewalPayment(ActiveRow $payment): void
    {
        $this->renewalPayment = $payment;
    }
}
