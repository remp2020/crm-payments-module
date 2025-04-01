<?php

namespace Crm\PaymentsModule\Tests\Models\Retention;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\Retention\RetentionAnalysis;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RetentionAnalysisJobsRepository;
use Crm\PaymentsModule\Repositories\VariableSymbolRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\Extension\ExtendActualExtension;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionExtensionMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionLengthMethodsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeNamesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Nette\Utils\Json;

class RetentionAnalysisTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;
    private PaymentsRepository $paymentsRepository;
    private RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository;
    private UserManager $userManager;
    private RetentionAnalysis $retentionAnalysis;

    protected function requiredRepositories(): array
    {
        return [
            PaymentGatewaysRepository::class,
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            RetentionAnalysisJobsRepository::class,
            SubscriptionsRepository::class,
            SubscriptionExtensionMethodsRepository::class,
            SubscriptionLengthMethodsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeItemsRepository::class,
            SubscriptionTypeNamesRepository::class,
            UsersRepository::class,
            VariableSymbolRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            SubscriptionTypeNamesSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->subscriptionsRepository = $this->inject(SubscriptionsRepository::class);
        $this->paymentsRepository = $this->inject(PaymentsRepository::class);
        $this->retentionAnalysisJobsRepository = $this->inject(RetentionAnalysisJobsRepository::class);
        $this->retentionAnalysis = $this->inject(RetentionAnalysis::class);

        $this->retentionAnalysis->setNow(DateTime::from('2023-07-01'));
        $this->preparePayments();
    }

    public function testRetentionAnalysisPartitionMonth()
    {
        $params = [
            'partition' => RetentionAnalysis::PARTITION_MONTH,
            'zero_period_length' => 31,
            'period_length' => 31,
        ];
        $job = $this->retentionAnalysisJobsRepository->add('test_analysis', Json::encode($params));

        $this->retentionAnalysis->runJob($job);

        $job = $this->retentionAnalysisJobsRepository->find($job->id);
        $this->assertEquals(RetentionAnalysisJobsRepository::STATE_FINISHED, $job->state);
        $this->assertEqualsCanonicalizing(
            [
                '2022-12' => [
                    [
                        'count' => 3,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 3,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 1,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                        'incomplete' => true,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                        'incomplete' => true,
                    ]
                ],
                '2023-01' => [
                    [
                        'count' => 2,
                        'users_in_period' => 2,
                    ],
                    [
                        'count' => 2,
                        'users_in_period' => 2,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 2,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 2,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 2,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 2,
                        'incomplete' => true,
                    ],
                ]
            ],
            Json::decode($job->results, forceArrays: true)['retention']
        );
    }

    public function testRetentionAnalysisPartitionWeek()
    {
        $params = [
            'partition' => RetentionAnalysis::PARTITION_WEEK,
            'zero_period_length' => 31,
            'period_length' => 31,
        ];
        $job = $this->retentionAnalysisJobsRepository->add('test_analysis', Json::encode($params));

        $this->retentionAnalysis->runJob($job);

        $job = $this->retentionAnalysisJobsRepository->find($job->id);
        $this->assertEquals(RetentionAnalysisJobsRepository::STATE_FINISHED, $job->state);
        $this->assertEqualsCanonicalizing(
            [
                '2022-48' => [
                    [
                        'count' => 1,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 1,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 1,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                        'incomplete' => true,
                    ],
                ],
                '2022-52' => [
                    [
                        'count' => 3,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 3,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 3,
                        'incomplete' => true,
                    ],
                ],
                '2023-01' => [
                    [
                        'count' => 1,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 1,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                    ],
                    [
                        'count' => 0,
                        'users_in_period' => 1,
                        'incomplete' => true,
                    ],
                ]
            ],
            Json::decode($job->results, forceArrays: true)['retention']
        );
    }

    private function preparePayments()
    {
        $subscriptionType = $this->getSubscriptionType();

        $data = [
            [
                'email' => 'example@example.com',
                'startTime' => new DateTime('2022-12-01'),
                'endTime' => new DateTime('2023-01-01'),
                'paidAt' => new DateTime('2022-12-01'),
            ],
            [
                'email' => 'example1@example.com',
                'startTime' => new DateTime('2022-12-27'),
                'endTime' => new DateTime('2023-01-27'),
                'paidAt' => new DateTime('2022-12-27'),
            ],
            [
                'email' => 'example2@example.com',
                'startTime' => new DateTime('2022-12-31'),
                'endTime' => new DateTime('2023-01-31'),
                'paidAt' => new DateTime('2022-12-31'),
            ],
            [
                'email' => 'example3@example.com',
                'startTime' => new DateTime('2023-01-01'),
                'endTime' => new DateTime('2023-02-01'),
                'paidAt' => new DateTime('2023-01-01'),
            ],
            [
                'email' => 'example4@example.com',
                'startTime' => new DateTime('2023-01-02'),
                'endTime' => new DateTime('2023-02-02'),
                'paidAt' => new DateTime('2023-01-02'),
            ],

            [
                'email' => 'example@example.com',
                'startTime' => new DateTime('2023-01-01'),
                'endTime' => new DateTime('2023-02-01'),
                'paidAt' => new DateTime('2023-01-01'),
            ]
        ];

        foreach ($data as $dataRow) {
            $user = $this->getUser($dataRow['email']);
            $subscription = $this->addSubscription(
                subscriptionType: $subscriptionType,
                user: $user,
                from: $dataRow['startTime'],
                to: $dataRow['endTime']
            );
            $payment = $this->addPayment($subscriptionType, $user, $dataRow['paidAt']);
            $this->paymentsRepository->addSubscriptionToPayment($subscription, $payment);
        }
    }

    private function getUser($email): ActiveRow
    {
        $user = $this->userManager->loadUserByEmail($email);
        if (!$user) {
            $user = $this->userManager->addNewUser($email);
        }

        return $user;
    }

    private function getSubscriptionType($price = 30)
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        $subscriptionTypeRow = $subscriptionTypeBuilder
            ->createNew()
            ->setNameAndUserLabel(random_int(0, 9999))
            ->setActive(1)
            ->setPrice($price)
            ->setLength(30)
            ->setExtensionMethod(ExtendActualExtension::METHOD_CODE)
            ->save();

        return $subscriptionTypeRow;
    }

    private function addSubscription(ActiveRow $subscriptionType, ActiveRow $user, string $type = SubscriptionsRepository::TYPE_REGULAR, DateTime $from = null, DateTime $to = null, bool $isPaid = true)
    {
        return $this->subscriptionsRepository->add(
            $subscriptionType,
            false,
            $isPaid,
            $user,
            $type,
            $from,
            $to
        );
    }

    private function addPayment(ActiveRow $subscriptionType, ActiveRow $user, DateTime $paidAt)
    {
        $pgr = $this->getRepository(PaymentGatewaysRepository::class);
        if (!$paymentGateway = $pgr->getTable()->where(['code' => 'test'])->fetch()) {
            $paymentGateway = $pgr->add('test', 'test', 10, true, true);
        }

        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItem(new SubscriptionTypePaymentItem(
            $subscriptionType->id,
            $subscriptionType->name,
            $subscriptionType->price,
            19,
            1,
        ));

        $payment = $this->paymentsRepository->add($subscriptionType, $paymentGateway, $user, $paymentItemContainer, null, $subscriptionType->price);
        $this->paymentsRepository->updateStatus($payment, PaymentStatusEnum::Paid->value);
        $this->paymentsRepository->update($payment, ['paid_at' => $paidAt]);

        return $this->paymentsRepository->find($payment->id);
    }

    final public function addSubscriptionToPayment(ActiveRow $subscription, ActiveRow $payment)
    {
        return $this->paymentsRepository->update($payment, ['subscription_id' => $subscription->id]);
    }
}
