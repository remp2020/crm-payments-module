<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\VariableSymbolInterface;
use Crm\PaymentsModule\Repositories\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentGatewayMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repositories\AccessTokensRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\DI\Container;

class PaymentsTestCase extends DatabaseTestCase
{
    /** @var  Container */
    protected $container;

    /** @var PaymentsRepository */
    protected $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    protected $recurrentPaymentsRepository;

    /** @var PaymentGatewaysRepository */
    protected $paymentGatewaysRepository;

    /** @var AccessTokensRepository */
    protected $accessTokensRepository;

    public function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
            PaymentGatewaysSeeder::class,
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
            RecurrentPaymentsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypesMetaRepository::class,
            SubscriptionTypeItemsRepository::class,
            VariableSymbolInterface::class,
            UsersRepository::class,
            ParsedMailLogsRepository::class
        ];
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->container = $GLOBALS['container'];

        $this->accessTokensRepository = $this->getRepository(AccessTokensRepository::class);
        $this->paymentsRepository = $this->getRepository(PaymentsRepository::class);
        $this->paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $this->recurrentPaymentsRepository = $this->getRepository(RecurrentPaymentsRepository::class);
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
            $this->user = $usersRepository->add('asfsaoihf@afasf.sk', 'q039uewt');
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
            $this->paymentGateway = $paymentGatewaysRepository->add('MyPay', 'my_pay');
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
