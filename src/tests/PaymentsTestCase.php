<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\VariableSymbolInterface;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesMetaRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Repository\AccessTokensRepository;
use Nette\DI\Container;

class PaymentsTestCase extends DatabaseTestCase
{
    /** @var  Container */
    protected $container;

    /** @var  \Crm\PaymentsModule\Repository\PaymentsRepository */
    protected $paymentsRepository;

    /** @var  \Crm\PaymentsModule\Repository\RecurrentPaymentsRepository */
    protected $recurrentPaymentsRepository;

    /** @var  \Crm\PaymentsModule\Repository\PaymentGatewaysRepository */
    protected $paymentGatewaysRepository;

    /** @var AccessTokensRepository */
    protected $accessTokensRepository;

    public function requiredSeeders(): array
    {
        return [
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
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
            RecurrentPaymentsRepository::class,
            SubscriptionTypesRepository::class,
            SubscriptionTypesMetaRepository::class,
            VariableSymbolInterface::class
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
            $usersRepository = $this->container->getByType('Crm\UsersModule\Repository\UsersRepository');
            $this->user = $usersRepository->add('asfsaoihf@afasf.sk', 'q039uewt', '', '', '', 1);
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
            $paymentGatewaysRepository = $this->container->getByType('Crm\PaymentsModule\Repository\PaymentGatewaysRepository');
            $this->paymentGateway = $paymentGatewaysRepository->add('MyPay', 'my_pay');
        }
        return $this->paymentGateway;
    }

    private $subscriptionType;

    protected function getSubscriptionType()
    {
        if (!$this->subscriptionType) {
            $subscriptionTypeBuilder = $this->container->getByType('Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder');
            $this->subscriptionType = $subscriptionTypeBuilder->createNew()
                ->setName('my subscription type')
                ->setUserLabel('my subscription type')
                ->setPrice(12.2)
                ->setCode('my_subscription_type')
                ->setLength(31)
                ->setActive(true)
                ->save();
        }
        return $this->subscriptionType;
    }
}
