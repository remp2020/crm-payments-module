<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentGatewayCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class PaymentGatewayCriteriaTest extends DatabaseTestCase
{
    /** @var SubscriptionsRepository */
    private $subscriptionsRepository;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var PaymentGatewayCriteria */
    private $paymentGatewayCriteria;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewayCriteria = $this->inject(PaymentGatewayCriteria::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            SubscriptionsRepository::class,
            PaymentsRepository::class,
            UsersRepository::class,
            PaymentGatewaysRepository::class,
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

    public function testCriteria(): void
    {
        [$userRow, $paymentRow, $paymentGatewayRow] = $this->prepareData('user1@example.com', 'free');
        $q = $this->paymentsRepository->getTable()->where('id = ?', $paymentRow->id);
        $wrongId = $paymentGatewayRow->id + 1;
        $this->assertTrue($this->paymentGatewayCriteria->addCondition($q, (object)['selection' => [$wrongId]], $paymentRow));
        $this->assertFalse($q->fetch());

        [$userRow, $paymentRow, $paymentGatewayRow] = $this->prepareData('user2@example.com', 'bank_transfer');
        $q = $this->paymentsRepository->getTable()->where('id = ?', $paymentRow->id);
        $this->assertTrue($this->paymentGatewayCriteria->addCondition($q, (object)['selection' => [$paymentGatewayRow->id]], $paymentRow));
        $this->assertNotFalse($q->fetch());
    }

    private function prepareData(string $userEmail, string $paymentGatewayCode): array
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser($userEmail);

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
            false,
            true,
            $userRow
        );

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', $paymentGatewayCode);

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

        return [$userRow, $paymentRow, $paymentGatewayRow];
    }
}
