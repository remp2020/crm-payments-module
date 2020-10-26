<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentStateCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class RecurrentPaymentStateCriteriaTest extends DatabaseTestCase
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
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData(RecurrentPaymentsRepository::STATE_ACTIVE);

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $this->assertTrue(
            $recurrentPaymentStateCriteria->addCondition($recurrentPaymentSelection, (object)['selection' => [RecurrentPaymentsRepository::STATE_ACTIVE]], $recurrentPaymentRow)
        );
        $this->assertNotFalse($recurrentPaymentSelection->fetch());
    }

    public function testPositiveResultWithMoreSelectedValues(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData(RecurrentPaymentsRepository::STATE_ACTIVE);

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $this->assertTrue(
            $recurrentPaymentStateCriteria->addCondition(
                $recurrentPaymentSelection,
                (object)['selection' => [
                    RecurrentPaymentsRepository::STATE_ACTIVE,
                    RecurrentPaymentsRepository::STATE_PENDING,
                    RecurrentPaymentsRepository::STATE_CHARGED,
                ]],
                $recurrentPaymentRow
            )
        );
        $this->assertNotFalse($recurrentPaymentSelection->fetch());
    }

    public function testNegativeResultWithOneSelectedValue(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData(RecurrentPaymentsRepository::STATE_SYSTEM_STOP);

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $this->assertTrue(
            $recurrentPaymentStateCriteria->addCondition($recurrentPaymentSelection, (object)['selection' => [RecurrentPaymentsRepository::STATE_CHARGED]], $recurrentPaymentRow)
        );
        $this->assertFalse($recurrentPaymentSelection->fetch());
    }

    public function testNegativeResultWithMoreSelectedValues(): void
    {
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData(RecurrentPaymentsRepository::STATE_SYSTEM_STOP);

        $recurrentPaymentStateCriteria = new RecurrentPaymentStateCriteria($this->recurrentPaymentsRepository);

        $this->assertTrue(
            $recurrentPaymentStateCriteria->addCondition(
                $recurrentPaymentSelection,
                (object)['selection' => [
                    RecurrentPaymentsRepository::STATE_ACTIVE,
                    RecurrentPaymentsRepository::STATE_CHARGED,
                    RecurrentPaymentsRepository::STATE_CHARGE_FAILED,
                ]],
                $recurrentPaymentRow
            )
        );
        $this->assertFalse($recurrentPaymentSelection->fetch());
    }

    private function prepareData(string $recurrentPaymentState): array
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
            $userRow
        );

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', 'paypal');

        $paymentRow = $this->paymentsRepository->add(
            $subscriptionTypeRow,
            $paymentGatewayRow,
            $userRow,
            new PaymentItemContainer(),
            null,
            1
        );

        $this->paymentsRepository->addSubscriptionToPayment($subscriptionRow, $paymentRow);

        $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentsRepository::STATUS_PAID);

        $recurrentPaymentRow = $this->recurrentPaymentsRepository->createFromPayment($paymentRow, 'recurrentToken');
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, ['state' => $recurrentPaymentState]);

        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()
            ->where(['id' => $recurrentPaymentRow->id]);

        return [$recurrentPaymentSelection, $recurrentPaymentRow];
    }
}
