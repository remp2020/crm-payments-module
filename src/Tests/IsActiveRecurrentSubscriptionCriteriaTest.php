<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Gateways\Paypal;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\IsActiveRecurrentSubscriptionCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class IsActiveRecurrentSubscriptionCriteriaTest extends DatabaseTestCase
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

    public function testIsActiveRecurrentSubscriptionWithRecurrentNotStopped(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(true, false);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => true];
        $this->assertTrue(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );

        $this->assertNotNull($subscriptionSelection->fetch());
    }

    public function testIsActiveRecurrentSubscriptionWithRecurrentStopped(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(true, true);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => true];
        $this->assertFalse(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );
    }

    public function testIsActiveRecurrentSubscriptionWithoutRecurrent(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(false, true);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => true];
        $this->assertFalse(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );
    }

    public function testIsNotActiveRecurrentSubscriptionWithRecurrentNotStopped(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(true, false);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => false];
        $this->assertFalse(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );
    }

    public function testIsNotActiveRecurrentSubscriptionWithRecurrentStopped(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(true, true);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);
        $values = (object)['selection' => false];
        $this->assertTrue(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );

        $this->assertNotNull($subscriptionSelection->fetch());
    }

    public function testIsNotActiveRecurrentSubscriptionWithoutRecurrent(): void
    {
        [$subscriptionSelection, $subscriptionRow] = $this->prepareData(false, true);

        $isActiveRecurrentSubscriptionCriteria = new IsActiveRecurrentSubscriptionCriteria($this->recurrentPaymentsRepository);

        $values = (object)['selection' => false];
        $this->assertTrue(
            $isActiveRecurrentSubscriptionCriteria->addConditions($subscriptionSelection, ['is_active_recurrent_subscription' => $values], $subscriptionRow)
        );

        $this->assertNotNull($subscriptionSelection->fetch());
    }

    private function prepareData(bool $isRecurrent, bool $isStoppedBySubscription): array
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
            $isRecurrent,
            true,
            $userRow
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
            1
        );

        $this->paymentsRepository->addSubscriptionToPayment($subscriptionRow, $paymentRow);

        $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentsRepository::STATUS_PAID);

        if ($isStoppedBySubscription === false) {
            $this->recurrentPaymentsRepository->createFromPayment($paymentRow, 'recurrentToken');
        }

        $subscriptionSelection = $this->subscriptionsRepository->getTable()
            ->where(['id' => $subscriptionRow->id]);

        return [$subscriptionSelection, $subscriptionRow];
    }
}
