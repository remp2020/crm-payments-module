<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Models;

use Crm\PaymentsModule\Models\RefundPaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;

class RefundPaymentProcessorTest extends PaymentsTestCase
{
    public function testProcessRefundedPaymentStoresRefundedAmount(): void
    {
        $payment = $this->createPayment('5157000001');

        $this->inject(RefundPaymentProcessor::class)->processRefundedPayment($payment, 12.5);

        $refundAmount = $this->getRepository(PaymentMetaRepository::class)
            ->findByPaymentAndKey($payment, RefundPaymentProcessor::REFUND_AMOUNT_META_KEY);
        $this->assertSame('12.5', $refundAmount->value);
    }
}
