<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentStateCriteria;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentStatusCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;

class RecurrentPaymentStatusCriteriaTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
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

    public function testPositiveResultWithOneSelectedValue(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData('01');

        $recurrentPaymentStateCriteria = new RecurrentPaymentStatusCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => ['01']];
        $this->assertTrue(
            $recurrentPaymentStateCriteria->addConditions($recurrentPaymentSelection, [RecurrentPaymentStatusCriteria::KEY => $values], $recurrentPaymentRow),
        );
        $this->assertNotNull($recurrentPaymentSelection->fetch());
    }

    public function testPositiveResultWithMoreSelectedValues(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData('01');

        $recurrentPaymentStateCriteria = new RecurrentPaymentStatusCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => [
            '01',
            '02',
            '03',
        ]];
        $this->assertTrue(
            $recurrentPaymentStateCriteria->addConditions(
                $recurrentPaymentSelection,
                [RecurrentPaymentStatusCriteria::KEY => $values],
                $recurrentPaymentRow,
            ),
        );
        $this->assertNotNull($recurrentPaymentSelection->fetch());
    }

    public function testNegativeResultWithOneSelectedValue(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData('01');

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => ['02']];
        $this->assertTrue(
            $recurrentPaymentStateCriteria->addConditions($recurrentPaymentSelection, [RecurrentPaymentStateCriteria::KEY => $values], $recurrentPaymentRow),
        );
        $this->assertNull($recurrentPaymentSelection->fetch());
    }

    public function testNegativeResultWithMoreSelectedValues(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData('01');

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => [
            '02',
            '03',
            '04',
        ]];
        $this->assertTrue(
            $recurrentPaymentStateCriteria->addConditions(
                $recurrentPaymentSelection,
                [RecurrentPaymentStateCriteria::KEY => $values],
                $recurrentPaymentRow,
            ),
        );
        $this->assertNull($recurrentPaymentSelection->fetch());
    }

    private function prepareData(string $recurrentPaymentStatus): array
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

        $subscriptionRow = $this->subscriptionsRepository->add(
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
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, ['status' => $recurrentPaymentStatus]);

        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()
            ->where(['id' => $recurrentPaymentRow->id]);

        return [$recurrentPaymentSelection, $recurrentPaymentRow];
    }
}
