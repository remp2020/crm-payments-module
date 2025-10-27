<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Table\ActiveRow;
use PHPUnit\Framework\Attributes\DataProvider;

class SubscriptionPauserTest extends PaymentsTestCase
{
    protected SubscriptionsRepository $subscriptionsRepository;

    protected SubscriptionPauser $subscriptionPauser;

    protected SubscriptionPausesRepository $subscriptionPausesRepository;

    public function requiredRepositories(): array
    {
        return parent::requiredRepositories() + [
            SubscriptionPausesRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->inject(\Crm\SubscriptionsModule\Repositories\SubscriptionsRepository::class);
        $this->subscriptionPauser = $this->inject(\Crm\PaymentsModule\Models\Subscription\SubscriptionPauser::class);
        $this->subscriptionPausesRepository = $this->inject(SubscriptionPausesRepository::class);
    }

    public static function dataProviderPauseSubscription(): array
    {
        return [
            'dryRunTest_-_noChanges' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                ],
                'recurrentPayments' => [],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => true,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => false,
                        'recurrent_payments_changes' => false,
                    ],
                    'subscriptions_count' => 1,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-31',
                        ],
                    ],
                    'recurrentPayments' => [],
                ],
            ],
            'simpleSplit_-_oneSubscription' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                ],
                'recurrentPayments' => [],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => false,
                        'recurrent_payments_changes' => false,
                    ],
                    'subscriptions_count' => 2,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                    ],
                    'recurrentPayments' => [],
                ],
            ],
            'splitAndMove_-_oneFutureSubscription' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                    [
                        'start_time' => '2023-02-01',
                        'end_date' => '2023-02-28',
                    ],
                ],
                'recurrentPayments' => [],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => true,
                        'recurrent_payments_changes' => false,
                    ],
                    'subscriptions_count' => 3,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                        [
                            'start_time' => '2023-02-22',
                            'end_date' => '2023-03-21',
                        ],
                    ],
                    'recurrentPayments' => [],
                ],
            ],
            'splitAndMove_-_oneFutureSubscription_-_doNotTouchOldSubscriptions' => [
                'subscriptions' => [
                    [
                        'start_time' => '2022-12-01',
                        'end_date' => '2022-12-31',
                    ],
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                    [
                        'start_time' => '2023-02-01',
                        'end_date' => '2023-02-28',
                    ],
                ],
                'recurrentPayments' => [],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => true,
                        'recurrent_payments_changes' => false,
                    ],
                    'subscriptions_count' => 4,
                    'subscriptions' => [
                        [
                            'start_time' => '2022-12-01',
                            'end_date' => '2022-12-31',
                        ],
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                        [
                            'start_time' => '2023-02-22',
                            'end_date' => '2023-03-21',
                        ],
                    ],
                    'recurrentPayments' => [],
                ],
            ],
            'splitAndMove_-_multipleFutureSubscription' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                    [
                        'start_time' => '2023-02-01',
                        'end_date' => '2023-02-28',
                    ],
                    [
                        'start_time' => '2023-03-01',
                        'end_date' => '2023-03-30',
                    ],
                ],
                'recurrentPayments' => [],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => true,
                        'recurrent_payments_changes' => false,
                    ],
                    'subscriptions_count' => 4,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                        [
                            'start_time' => '2023-02-22',
                            'end_date' => '2023-03-21',
                        ],
                        [
                            'start_time' => '2023-03-22',
                            'end_date' => '2023-04-20',
                        ],
                    ],
                    'recurrentPayments' => [],
                ],
            ],
            'splitAndMove_-_oneRecurrentChargeAt' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                ],
                'recurrentPayments' => [
                    [
                        'charge_at' => '2023-01-29',
                    ],
                ],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => false,
                        'recurrent_payments_changes' => true,
                    ],
                    'subscriptions_count' => 2,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                    ],
                    'recurrentPayments' => [
                        [
                            'charge_at' => '2023-02-19',
                        ],
                    ],
                ],
            ],
            'splitAndMove_-_multipleRecurrentChargeAt' => [
                'subscriptions' => [
                    [
                        'start_time' => '2023-01-01',
                        'end_date' => '2023-01-31',
                        'pause_subscription' => true,
                    ],
                    [
                        'start_time' => '2023-02-01',
                        'end_date' => '2023-02-28',
                    ],
                ],
                'recurrentPayments' => [
                    [
                        'charge_at' => '2023-01-29',
                    ],
                    [
                        'charge_at' => '2023-02-26',
                    ],
                ],
                'pauseAt' => '2023-01-10',
                'resumeAt' => '2023-01-31',
                'dryRun' => false,

                'expected' => [
                    'change_previews' => [
                        'active_subscription_changes' => true,
                        'future_subscription_changes' => true,
                        'recurrent_payments_changes' => true,
                    ],
                    'subscriptions_count' => 3,
                    'subscriptions' => [
                        [
                            'start_time' => '2023-01-01',
                            'end_date' => '2023-01-10',
                        ],
                        [
                            'start_time' => '2023-01-31',
                            'end_date' => '2023-02-21',
                        ],
                        [
                            'start_time' => '2023-02-22',
                            'end_date' => '2023-03-21',
                        ],
                    ],
                    'recurrentPayments' => [
                        [
                            'charge_at' => '2023-02-19',
                        ],
                        [
                            'charge_at' => '2023-03-19',
                        ],
                    ],
                ],
            ],
        ];
    }

    #[DataProvider('dataProviderPauseSubscription')]
    public function testPauseSubscription(
        array $subscriptions,
        array $recurrentPayments,
        string $pauseAt,
        string $resumeAt,
        bool $dryRun,
        array $expected,
    ): void {
        $subscriptionType = $this->getSubscriptionType();
        $user = $this->getUser();

        $pauseSubscription = null;
        foreach ($subscriptions as $subscriptionData) {
            $subscription = $this->subscriptionsRepository->add(
                subscriptionType: $subscriptionType,
                isRecurrent: false,
                isPaid: false,
                user: $user,
                type: SubscriptionsRepository::TYPE_REGULAR,
                startTime: new \DateTime($subscriptionData['start_time']),
                endTime: new \DateTime($subscriptionData['end_date']),
            );

            if (isset($subscriptionData['pause_subscription']) && $subscriptionData['pause_subscription']) {
                $pauseSubscription = $subscription;
            }
        }

        foreach ($recurrentPayments as $recurrentPaymentData) {
            $paymentMethod = $this->getPaymentMethod();
            $payment = $this->createPayment('123');
            $this->createRecurrentPayment(
                paymentMethod: $paymentMethod,
                payment: $payment,
                chargeAt: new \DateTime($recurrentPaymentData['charge_at']),
            );
        }

        $this->subscriptionPauser->setDryRun($dryRun);
        $changes = $this->subscriptionPauser->pauseSubscription(
            $pauseSubscription,
            new \DateTime($pauseAt),
            new \DateTime($resumeAt),
        );

        if ($expected['change_previews']['active_subscription_changes']) {
            $this->assertNotEmpty($changes[SubscriptionPauser::ACTIVE_SUBSCRIPTION_CHANGES]);
        } else {
            $this->assertEmpty($changes[SubscriptionPauser::ACTIVE_SUBSCRIPTION_CHANGES]);
        }

        if ($expected['change_previews']['future_subscription_changes']) {
            $this->assertNotEmpty($changes[SubscriptionPauser::FUTURE_SUBSCRIPTION_CHANGES]);
        } else {
            $this->assertEmpty($changes[SubscriptionPauser::FUTURE_SUBSCRIPTION_CHANGES]);
        }

        if ($expected['change_previews']['recurrent_payments_changes']) {
            $this->assertNotEmpty($changes[SubscriptionPauser::RECURRENT_PAYMENTS_CHANGES]);
        } else {
            $this->assertEmpty($changes[SubscriptionPauser::RECURRENT_PAYMENTS_CHANGES]);
        }

        $subscriptionsCount = $this->subscriptionsRepository->userSubscriptions($user->id)->count('*');
        $this->assertEquals($expected['subscriptions_count'], $subscriptionsCount);

        foreach ($expected['subscriptions'] as $expectedSubscription) {
            $subscription = $this->subscriptionsRepository->getTable()
                ->where('start_time', new \DateTime($expectedSubscription['start_time']))
                ->where('end_time', new \DateTime($expectedSubscription['end_date']))
                ->fetch();
            $this->assertNotNull($subscription);
        }

        foreach ($expected['recurrentPayments'] as $expectedRecurrentPayment) {
            $recurrentPayment = $this->recurrentPaymentsRepository->getTable()
                ->where('charge_at', new \DateTime($expectedRecurrentPayment['charge_at']))
                ->fetch();
            $this->assertNotNull($recurrentPayment);
        }

        if ($dryRun) {
            // dry run should not create a pause record
            $this->assertEquals(0, $this->subscriptionPausesRepository->all()->count('*'));
        } else {
            $this->assertEquals(1, $this->subscriptionPausesRepository->all()->count('*'));
        }
    }

    protected function createRecurrentPayment(ActiveRow $paymentMethod, ActiveRow $payment, \DateTime $chargeAt): void
    {
        $this->recurrentPaymentsRepository->add(
            paymentMethod: $paymentMethod,
            payment: $payment,
            chargeAt: $chargeAt,
            customAmount: null,
            retries: 5,
        );
    }

    protected function getPaymentMethod(): ActiveRow
    {
        return $this->paymentMethodsRepository->add(
            userId: $this->getUser()->id,
            paymentGatewayId: $this->getPaymentGateway()->id,
            externalToken: 'test-token' . random_int(0, 1000),
        );
    }
}
