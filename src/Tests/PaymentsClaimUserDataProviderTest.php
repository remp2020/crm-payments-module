<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\DataProviders\PaymentsClaimUserDataProvider;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\UsersModule\Models\User\UnclaimedUser;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Crm\UsersModule\Seeders\UsersSeeder;

class PaymentsClaimUserDataProviderTest extends DatabaseTestCase
{
    private $dataProvider;

    /** @var GatewayFactory */
    private $gatewayFactory;

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var PaymentGatewaysRepository */
    private $paymentGatewaysRepository;

    /** @var UsersRepository */
    private $usersRepository;

    /** @var UnclaimedUser */
    private $unclaimedUser;

    private $unclaimedUserObj;

    private $loggedUser;

    protected function requiredRepositories(): array
    {
        return [
            PaymentsRepository::class,
            PaymentGatewaysRepository::class,
            UsersRepository::class,
            UserMetaRepository::class
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
            UsersSeeder::class
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dataProvider = $this->inject(PaymentsClaimUserDataProvider::class);

        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $this->unclaimedUser = $this->inject(UnclaimedUser::class);
        $this->usersRepository = $this->getRepository(UsersRepository::class);
        $this->gatewayFactory = $this->inject(GatewayFactory::class);
        $this->gatewayFactory->registerGateway(Paypal::GATEWAY_CODE);

        $this->unclaimedUserObj = $this->unclaimedUser->createUnclaimedUser();
        $this->loggedUser = $this->usersRepository->getByEmail(UsersSeeder::USER_ADMIN);
    }

    public function testWrongArguments(): void
    {
        $this->expectException(DataProviderException::class);
        $this->dataProvider->provide([]);
    }

    public function testClaimUserPayments(): void
    {
        $paymentGateway = $this->paymentGatewaysRepository->findByCode(Paypal::GATEWAY_CODE);
        $addedPayment = $this->paymentsRepository->add(null, $paymentGateway, $this->unclaimedUserObj, new PaymentItemContainer(), null, 1, null, null, null, 1, null, '154');

        $this->dataProvider->provide(['unclaimedUser' => $this->unclaimedUserObj, 'loggedUser' => $this->loggedUser]);

        $this->assertEmpty($this->paymentsRepository->userPayments($this->unclaimedUserObj->id)->fetchAll());

        $loggedUserPayments = $this->paymentsRepository->userPayments($this->loggedUser->id);
        $this->assertCount(1, $loggedUserPayments->fetchAll());
        $this->assertEquals($addedPayment->variable_symbol, $loggedUserPayments->fetch()->variable_symbol);
    }
}
