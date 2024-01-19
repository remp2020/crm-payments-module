<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\DataProviders\RecurrentPaymentsClaimUserDataProvider;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;
use Nette\Utils\DateTime;

class RecurrentPaymentsClaimUserDataProviderTest extends DatabaseTestCase
{
    private $dataProvider;

    /** @var GatewayFactory */
    private $gatewayFactory;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var PaymentGatewaysRepository */
    private $paymentGatewaysRepository;

    /** @var SubscriptionTypeBuilder */
    private $subscriptionTypeBuilder;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $unclaimedUserObj;

    private $loggedUser;

    private $payment;

    protected function requiredRepositories(): array
    {
        return [
            PaymentsRepository::class,
            PaymentGatewaysRepository::class,
            RecurrentPaymentsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypeItemsRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
            UsersSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataProvider = $this->inject(RecurrentPaymentsClaimUserDataProvider::class);

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->gatewayFactory = $this->inject(GatewayFactory::class);

        $this->gatewayFactory->registerGateway(Paypal::GATEWAY_CODE);
        $paymentGateway = $this->paymentGatewaysRepository->findByCode(Paypal::GATEWAY_CODE);
        $this->subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        $subscriptionType = $this->subscriptionTypeBuilder->createNew()
            ->setNameAndUserLabel('Online mesiac - iba web')
            ->setCode('web_month')
            ->setPrice(4.9)
            ->setLength(31)
            ->setSorting(10)
            ->setActive(true)
            ->setVisible(true)
            ->setDescription('web')
            ->setContentAccessOption('web')
            ->save();

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN);

        $this->payment = $this->paymentsRepository->add($subscriptionType, $paymentGateway, $this->unclaimedUserObj, new PaymentItemContainer(), null, 1, null, null, null, 1, null, '154');
    }

    public function testWrongArguments(): void
    {
        $this->expectException(DataProviderException::class);
        $this->dataProvider->provide([]);
    }

    public function testClaimUserRecurrentPayments(): void
    {
        $recurrentPayment = $this->recurrentPaymentsRepository->add('322520', $this->payment, new DateTime(), 1, 1);

        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEmpty($this->recurrentPaymentsRepository->userRecurrentPayments($this->unclaimedUserObj->id)->fetchAll());

        $loggedUserRecurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($this->loggedUser->id);
        $this->assertCount(1, $loggedUserRecurrentPayments->fetchAll());
        $this->assertEquals($recurrentPayment->id, $loggedUserRecurrentPayments->fetch()->id);
    }
}
