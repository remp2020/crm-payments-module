<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Gateways\Paypal;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentSubscriptionTypeContentAccessCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;
use Nette\Database\Table\ActiveRow;

class RecurrentPaymentSubscriptionTypeContentAccessCriteriaTest extends DatabaseTestCase
{
    private SubscriptionsRepository $subscriptionsRepository;
    private PaymentsRepository $paymentsRepository;
    private RecurrentPaymentsRepository $recurrentPaymentsRepository;

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

    public function testRequiredAtLeastWebAccess(): void
    {
        $recurrentPaymentRow = $this->prepareData(['web']);

        /** @var RecurrentPaymentSubscriptionTypeContentAccessCriteria $criteria */
        $criteria = $this->inject(RecurrentPaymentSubscriptionTypeContentAccessCriteria::class);

        // first test non-matching
        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()->where(['id' => $recurrentPaymentRow->id]);
        $values = (object)['selection' => ['print']];
        $this->assertFalse(
            $criteria->addConditions($recurrentPaymentSelection, [RecurrentPaymentSubscriptionTypeContentAccessCriteria::KEY => $values], $recurrentPaymentRow)
        );

        // second test matching
        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()->where(['id' => $recurrentPaymentRow->id]);
        $values = (object)['selection' => ['web']];
        $this->assertTrue(
            $criteria->addConditions($recurrentPaymentSelection, [RecurrentPaymentSubscriptionTypeContentAccessCriteria::KEY => $values], $recurrentPaymentRow)
        );
        $this->assertNotNull($recurrentPaymentSelection->fetch());

        // second test matching (because of OR between selected content access names)
        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()->where(['id' => $recurrentPaymentRow->id]);
        $values = (object)['selection' => ['web', 'print']];
        $this->assertTrue(
            $criteria->addConditions($recurrentPaymentSelection, [RecurrentPaymentSubscriptionTypeContentAccessCriteria::KEY => $values], $recurrentPaymentRow)
        );
        $this->assertNotNull($recurrentPaymentSelection->fetch());
    }

    private function prepareData(array $contentAccessOptions): ActiveRow
    {
        /** @var UserManager $userManager */
        $userManager = $this->inject(UserManager::class);
        $userRow = $userManager->addNewUser('test@test.sk');

        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test_sub')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->setContentAccessOption(...$contentAccessOptions)
            ->save();

        $subscriptionRow = $this->subscriptionsRepository->add(
            $subscriptionTypeRow,
            true,
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
        $recurrentPaymentRow = $this->recurrentPaymentsRepository->createFromPayment($paymentRow, 'recurrentToken');
        $this->assertNotNull($recurrentPaymentRow);
        return $recurrentPaymentRow;
    }
}
