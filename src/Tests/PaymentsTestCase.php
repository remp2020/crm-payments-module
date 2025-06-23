<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\ApplicationModule\Seeders\CountriesSeeder;
use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\VariableSymbolInterface;
use Crm\PaymentsModule\Repositories\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentCardsRepository;
use Crm\PaymentsModule\Repositories\PaymentGatewayMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Seeders\ConfigsSeeder;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\CountriesRepository;
use Crm\UsersModule\Repositories\UsersRepository;

class PaymentsTestCase extends DatabaseTestCase
{
    public const TEST_GATEWAY_CODE = 'my_pay';
    protected PaymentsRepository $paymentsRepository;
    protected PaymentItemsRepository $paymentItemsRepository;
    protected PaymentMethodsRepository $paymentMethodsRepository;
    protected RecurrentPaymentsRepository $recurrentPaymentsRepository;
    protected PaymentGatewaysRepository $paymentGatewaysRepository;
    protected AccessTokensRepository $accessTokensRepository;
    protected PaymentCardsRepository $paymentCardsRepository;

    public function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
            ConfigsSeeder::class,
            CountriesSeeder::class,
            ContentAccessSeeder::class,
        ];
    }

    public function requiredRepositories(): array
    {
        return [
            AccessTokensRepository::class,
            PaymentsRepository::class,
            PaymentMetaRepository::class,
            PaymentItemsRepository::class,
            PaymentItemMetaRepository::class,
            PaymentGatewaysRepository::class,
            PaymentGatewayMetaRepository::class,
            PaymentMethodsRepository::class,
            PaymentCardsRepository::class,
            RecurrentPaymentsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypesMetaRepository::class,
            SubscriptionTypeItemsRepository::class,
            VariableSymbolInterface::class,
            UsersRepository::class,
            ParsedMailLogsRepository::class,
            ConfigsRepository::class,
            CountriesRepository::class,
            SubscriptionsRepository::class,
        ];
    }

    public function setUp(): void
    {
        parent::setUp();

        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentItemsRepository = $this->getRepository(PaymentItemsRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $this->paymentMethodsRepository = $this->getRepository(PaymentMethodsRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
        $this->paymentCardsRepository = $this->getRepository(PaymentCardsRepository::class);

        $gatewayFactory = $this->inject(GatewayFactory::class);
        $gatewayFactory->registerGateway(self::TEST_GATEWAY_CODE);
    }

    protected function createPayment($variableSymbol)
    {
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($this->getSubscriptionType()));
        $payment = $this->paymentsRepository->add($this->getSubscriptionType(), $this->getPaymentGateway(), $this->getUser(), $paymentItemContainer);
        $this->paymentsRepository->update($payment, ['variable_symbol' => $variableSymbol]);
        return $payment;
    }

    private $user;

    protected function getUser()
    {
        if (!$this->user) {
            /** @var UsersRepository $usersRepository */
            $usersRepository = $this->container->getByType(UsersRepository::class);
            $this->user = $usersRepository->add('asfsaoihf@example.com', 'q039uewt');
        }
        return $this->user;
    }

    private $paymentGateway = false;

    protected function getPaymentGateway()
    {
        if (!$this->container->hasService('my_payConfig')) {
            $this->container->addService('my_payConfig', new TestPaymentConfig());
        }
        if (!$this->paymentGateway) {
            $paymentGatewaysRepository = $this->container->getByType(PaymentGatewaysRepository::class);
            $this->paymentGateway = $paymentGatewaysRepository->add('MyPay', self::TEST_GATEWAY_CODE);
        }
        return $this->paymentGateway;
    }

    private $subscriptionType;

    protected function getSubscriptionType()
    {
        if (!$this->subscriptionType) {
            /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
            $subscriptionTypeBuilder = $this->container->getByType(SubscriptionTypeBuilder::class);
            $this->subscriptionType = $subscriptionTypeBuilder->createNew()
                ->setName('my subscription type')
                ->setUserLabel('my subscription type')
                ->setPrice(12.2)
                ->addSubscriptionTypeItem('first item', 10, 20)
                ->addSubscriptionTypeItem('second item', 2.2, 20)
                ->setCode('my_subscription_type')
                ->setLength(31)
                ->setActive(true)
                ->save();
        }
        return $this->subscriptionType;
    }
}
