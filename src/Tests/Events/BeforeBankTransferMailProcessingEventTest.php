<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Events;

use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\PaymentsModule\Events\BeforeBankTransferMailProcessingEvent;

class BeforeBankTransferMailProcessingEventTest extends CrmTestCase
{
    private readonly ActiveRowFactory $activeRowFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testDefaultState(): void
    {
        $payment = $this->activeRowFactory->create(['id' => 1]);

        $event = new BeforeBankTransferMailProcessingEvent($payment);

        $this->assertSame($payment, $event->payment);
        $this->assertTrue($event->isPaymentGatewayOverrideAllowed());
    }

    public function testFlagAsPrevent(): void
    {
        $payment = $this->activeRowFactory->create(['id' => 1]);

        $event = new BeforeBankTransferMailProcessingEvent($payment);
        $this->assertTrue($event->isPaymentGatewayOverrideAllowed());

        $event->preventPaymentGatewayOverride();
        $this->assertFalse($event->isPaymentGatewayOverrideAllowed());
    }
}
