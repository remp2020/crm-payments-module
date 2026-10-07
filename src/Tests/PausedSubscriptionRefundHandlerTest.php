<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\Events\PausedSubscriptionRefundHandler;
use Crm\PaymentsModule\Events\PaymentChangeStatusEvent;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use Nette\Database\Table\ActiveRow;

class PausedSubscriptionRefundHandlerTest extends PaymentsTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;
    private PausedSubscriptionRefundHandler $handler;

    public function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            SubscriptionPausesRepository::class,
            SubscriptionMetaRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->handler = $this->inject(PausedSubscriptionRefundHandler::class);
        $this->inject(SubscriptionPauser::class)->setNow(new DateTime('2023-01-15'));
    }

    protected function tearDown(): void
    {
        $this->inject(SubscriptionPauser::class)->setNow(null);

        parent::tearDown();
    }

    public function testRefundStopsResumedSubscription(): void
    {
        [$paused, $resumed] = $this->createPausedSubscription();
        $payment = $this->createPayment('123');
        $this->paymentsRepository->update($payment, [
            'subscription_id' => $paused->id,
            'status' => PaymentStatusEnum::Refund->value,
        ]);

        $this->handler->handle(new PaymentChangeStatusEvent($payment));

        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertEquals(new DateTime('2023-01-15'), $resumed->start_time);
        $this->assertEquals(new DateTime('2023-01-15'), $resumed->end_time);
    }

    public function testOtherStatusIsIgnored(): void
    {
        [$paused, $resumed] = $this->createPausedSubscription();
        $payment = $this->createPayment('123');
        $this->paymentsRepository->update($payment, [
            'subscription_id' => $paused->id,
            'status' => PaymentStatusEnum::Paid->value,
        ]);

        $this->handler->handle(new PaymentChangeStatusEvent($payment));

        $this->assertResumedUntouched($resumed);
    }

    public function testRefundWithoutSubscriptionIsIgnored(): void
    {
        [, $resumed] = $this->createPausedSubscription();
        $payment = $this->createPayment('123');
        $this->paymentsRepository->update($payment, ['status' => PaymentStatusEnum::Refund->value]);

        $this->handler->handle(new PaymentChangeStatusEvent($payment));

        $this->assertResumedUntouched($resumed);
    }

    private function assertResumedUntouched(ActiveRow $resumed): void
    {
        $resumed = $this->subscriptionsRepository->find($resumed->id);
        $this->assertEquals(new DateTime('2023-01-31'), $resumed->start_time);
        $this->assertEquals(new DateTime('2023-02-21'), $resumed->end_time);
    }

    /**
     * @return ActiveRow[]
     */
    private function createPausedSubscription(): array
    {
        $paused = $this->createSubscription(new DateTime('2023-01-01'), new DateTime('2023-01-31'));

        return [$paused, $this->pauseSubscription($paused, new DateTime('2023-01-10'), new DateTime('2023-01-31'))];
    }
}
