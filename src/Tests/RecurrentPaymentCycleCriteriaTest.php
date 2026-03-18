<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentCycleCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;

class RecurrentPaymentCycleCriteriaTest extends DatabaseTestCase
{
    private RecurrentPaymentsRepository $recurrentPaymentsRepository;
    private PaymentsRepository $paymentsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            PaymentsRepository::class,
            UsersRepository::class,
            PaymentGatewaysRepository::class,
            RecurrentPaymentsRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            ContentAccessSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
        ];
    }

    #[DataProvider('dataProviderForTestCriteria')]
    public function testCriteria(int $recurrentPaymentCycle, string $criteriaOperator, int $criteriaCycle, bool $shouldFetch): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData($recurrentPaymentCycle);

        $criteria = $this->inject(RecurrentPaymentCycleCriteria::class);
        $criteria->addConditions(
            $recurrentPaymentSelection,
            [RecurrentPaymentCycleCriteria::KEY => (object)['selection' => $criteriaCycle, 'operator' => $criteriaOperator]],
            $recurrentPaymentRow,
        );

        if ($shouldFetch) {
            $this->assertNotNull($recurrentPaymentSelection->fetch());
        } else {
            $this->assertNull($recurrentPaymentSelection->fetch());
        }
    }

    public static function dataProviderForTestCriteria(): array
    {
        return [
            'equals_match' => [3, '=', 3, true],
            'equals_no_match' => [3, '=', 5, false],
            'greater_than_match' => [5, '>', 3, true],
            'greater_than_no_match' => [1, '>', 3, false],
            'less_than_match' => [2, '<', 5, true],
            'less_than_no_match' => [5, '<', 2, false],
            'greater_or_equal_match_equal' => [4, '>=', 4, true],
            'greater_or_equal_match_greater' => [6, '>=', 4, true],
            'greater_or_equal_no_match' => [2, '>=', 4, false],
            'less_or_equal_match_equal' => [3, '<=', 3, true],
            'less_or_equal_match_less' => [2, '<=', 3, true],
            'less_or_equal_no_match' => [5, '<=', 3, false],
        ];
    }

    public function testInvalidOperatorThrowsException(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData(1);

        $criteria = $this->inject(RecurrentPaymentCycleCriteria::class);

        $this->expectException(\Exception::class);
        $criteria->addConditions(
            $recurrentPaymentSelection,
            [RecurrentPaymentCycleCriteria::KEY => (object)['selection' => 1, 'operator' => '!=']],
            $recurrentPaymentRow,
        );
    }

    private function prepareData(int $cycle): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        $subscriptionRow = $this->getRepository(SubscriptionsRepository::class)->add(
            $subscriptionTypeRow,
            true,
            true,
            $userRow,
        );

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', Paypal::GATEWAY_CODE);

        $paymentRow = $this->paymentsRepository->add(
            $subscriptionTypeRow,
            $paymentGatewayRow,
            $userRow,
            new PaymentItemContainer(),
            null,
            1,
        );

        $this->paymentsRepository->addSubscriptionToPayment($subscriptionRow, $paymentRow);
        $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentStatusEnum::Paid->value);

        $recurrentPaymentRow = $this->recurrentPaymentsRepository->createFromPayment($paymentRow, 'recurrentToken');
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, ['cycle' => $cycle]);
        $recurrentPaymentRow = $this->recurrentPaymentsRepository->find($recurrentPaymentRow->id);

        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()
            ->where(['id' => $recurrentPaymentRow->id]);

        return [$recurrentPaymentSelection, $recurrentPaymentRow];
    }
}
