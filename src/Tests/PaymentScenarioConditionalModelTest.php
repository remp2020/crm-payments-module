<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentScenarioConditionModel;
use Crm\ProductsModule\Repositories\OrdersRepository;
use Crm\ProductsModule\Tests\BaseTestCase;
use Crm\ScenariosModule\Events\ConditionCheckException;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Repositories\UsersRepository;

class PaymentScenarioConditionalModelTest extends BaseTestCase
{
    private SubscriptionTypeBuilder $subscriptionTypeBuilder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
    }

    protected function requiredRepositories(): array
    {
        return [
            ...parent::requiredRepositories(),
            OrdersRepository::class,
        ];
    }

    public function testItemQuery(): void
    {
        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('test')
            ->setLength(31)
            ->setPrice(1)
            ->setActive(1)
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $gateway = $paymentGatewaysRepository->add('Gateway 1', 'gateway1');

        /** @var UsersRepository $usersRepository */
        $usersRepository = $this->getRepository(UsersRepository::class);
        $user = $usersRepository->add('usr1@crm.press', 'nbu12345');

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $payment = $paymentsRepository->add(
            $subscriptionType,
            $gateway,
            $user,
            new PaymentItemContainer(),
            amount: 1,
        );

        $paymentScenarioConditionModel = new PaymentScenarioConditionModel($paymentsRepository);
        $selection = $paymentScenarioConditionModel->getItemQuery((object) [
            'payment_id' => $payment->id,
        ]);

        $this->assertCount(1, $selection->fetchAll());
    }

    public function testItemQueryWithWrongId(): void
    {
        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $paymentScenarioConditionModel = new PaymentScenarioConditionModel($paymentsRepository);
        $selection = $paymentScenarioConditionModel->getItemQuery((object) [
            'payment_id' => 1,
        ]);

        $this->assertEmpty($selection->fetchAll());
    }

    public function testItemQueryWithoutMandatoryJobParameter(): void
    {
        $this->expectException(ConditionCheckException::class);
        $this->expectExceptionMessage("Payment scenario conditional model requires 'payment_id' job param.");

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $paymentScenarioConditionModel = new PaymentScenarioConditionModel($paymentsRepository);
        $paymentScenarioConditionModel->getItemQuery((object) []);
    }
}
