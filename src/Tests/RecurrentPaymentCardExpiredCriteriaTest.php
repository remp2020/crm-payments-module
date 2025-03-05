<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Scenarios\RecurrentPaymentCardExpiredCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Utils\DateTime;

class RecurrentPaymentCardExpiredCriteriaTest extends DatabaseTestCase
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

    public function testNegativeResult(): void
    {
        $chargeAt = new DateTime();
        $expiresAt = (clone $chargeAt)->add(new \DateInterval('P1M')); // 1 month

        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData($chargeAt, $expiresAt);

        $criteria = $this->inject(RecurrentPaymentCardExpiredCriteria::class);
        $this->assertTrue(
            $criteria->addConditions($recurrentPaymentSelection, [], $recurrentPaymentRow)
        );
        $this->assertNull($recurrentPaymentSelection->fetch());
    }

    public function testNegativeResultWithEmptyExpiresAt(): void
    {
        $chargeAt = new DateTime();
        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData($chargeAt, null);

        $criteria = $this->inject(RecurrentPaymentCardExpiredCriteria::class);
        $this->assertTrue(
            $criteria->addConditions($recurrentPaymentSelection, [], $recurrentPaymentRow)
        );
        $this->assertNull($recurrentPaymentSelection->fetch());
    }

    public function testPositiveResult(): void
    {
        $chargeAt = new DateTime();
        $expiresAt = (clone $chargeAt)->sub(new \DateInterval('P1M')); // 1 month

        [$recurrentPaymentSelection, $recurrentPaymentRow] = $this->prepareData($chargeAt, $expiresAt);

        $criteria = $this->inject(RecurrentPaymentCardExpiredCriteria::class);
        $this->assertTrue(
            $criteria->addConditions($recurrentPaymentSelection, [], $recurrentPaymentRow)
        );
        $this->assertNotNull($recurrentPaymentSelection->fetch());
    }

    private function prepareData(DateTime $chargeAt, ?DateTime $expiresAt): array
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
        $this->recurrentPaymentsRepository->update($recurrentPaymentRow, [
            'charge_at' => $chargeAt,
            'expires_at' => $expiresAt,
        ]);

        $recurrentPaymentRow = $this->recurrentPaymentsRepository->find($recurrentPaymentRow->id); // reload

        $recurrentPaymentSelection = $this->recurrentPaymentsRepository->getTable()
            ->where(['id' => $recurrentPaymentRow->id]);

        return [$recurrentPaymentSelection, $recurrentPaymentRow];
    }
}
