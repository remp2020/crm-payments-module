<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Recurrent;

use Crm\ApplicationModule\Repositories\ConfigsRepository;
use Crm\PaymentsModule\Commands\RecurrentPaymentsChargeCommand;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolutionTypeEnum;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Seeders\TestPaymentGatewaysSeeder;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\PrintModule\Seeders\AddressTypesSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\CountriesRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class RecurrentPaymentCreationTest extends PaymentsTestCase
{
    private PaymentProcessor $paymentsProcessor;

    private RecurrentPaymentsChargeCommand $recurrentPaymentsChargeCommand;

    private ActiveRow $user;


    public function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();
        $this->paymentsProcessor = $this->inject(PaymentProcessor::class);
        $this->recurrentPaymentsChargeCommand = $this->inject(RecurrentPaymentsChargeCommand::class);

        $configsRepository = $this->inject(ConfigsRepository::class);
        $donationVatRateConfig = $configsRepository->loadByName('donation_vat_rate');
        $configsRepository->update($donationVatRateConfig, ['value' => 0]);

        $gatewayFactory = $this->inject(GatewayFactory::class);
        $gatewayFactory->registerGateway(TestRecurrentGateway::GATEWAY_CODE, TestRecurrentGateway::class);

        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('user@example.com', false);

        $this->recurrentPaymentsChargeCommand->setFastChargeThreshold(0);
    }

    public function requiredSeeders(): array
    {
        return [
            ...parent::requiredSeeders(),
            AddressTypesSeeder::class,
            TestPaymentGatewaysSeeder::class,
        ];
    }

    public function testOneStopShopCountryResolution(): void
    {
        // Enable one-stop-shop
        $configsRepository = $this->inject(ConfigsRepository::class);
        $configRow = $configsRepository->loadByName('one_stop_shop_enabled');
        $configsRepository->update($configRow, ['value' => 1]);

        $st = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));

        $payment = $this->paymentsRepository->add(
            subscriptionType: $st,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
            paymentCountry: $this->inject(CountriesRepository::class)->findByIsoCode('PL'),
            paymentCountryResolutionReason: CountryResolutionTypeEnum::UserSelected->value,
        );

        // Make manual payment
        $this->paymentsProcessor->complete($payment, fn() => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $payment->status);
        $this->assertEquals('PL', $payment->payment_country->iso_code);
        $this->assertEquals(CountryResolutionTypeEnum::UserSelected->value, $payment->payment_country_resolution_reason);

        // Charge recurrent
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        $recurrentPayment = $this->chargeNow($recurrentPayment);

        // Check
        $payment2 = $recurrentPayment->payment;
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $payment2->status);
        $this->assertEquals('PL', $payment2->payment_country->iso_code);
        $this->assertEquals(CountryResolutionTypeEnum::PreviousPayment->value, $payment2->payment_country_resolution_reason);

        // Charge again
        $recurrentPayment2 = $this->recurrentPaymentsRepository->recurrent($payment2);
        $recurrentPayment2 = $this->chargeNow($recurrentPayment2);

        // Check again
        $payment3 = $recurrentPayment2->payment;
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $payment3->status);
        $this->assertEquals('PL', $payment3->payment_country->iso_code);
        $this->assertEquals(CountryResolutionTypeEnum::PreviousPayment->value, $payment3->payment_country_resolution_reason);
    }

    public function testNonRecurrentDonation(): void
    {
        $st = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st));
        $originalAmountWithoutDonation = $paymentItemContainer->totalPrice();
        $paymentItemContainer->addItem(new DonationPaymentItem('donation', 66, 0));

        $payment = $this->paymentsRepository->add(
            subscriptionType: $st,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
            additionalAmount: 66,
            additionalType: 'single'
        );

        // Make manual payment
        $this->paymentsProcessor->complete($payment, fn() => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $payment->status);
        $this->verifyPaymentItemsTypesAndAmounts($payment, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
            ['type' => DonationPaymentItem::TYPE, 'amount' => 66],
        ]);

        // Charge recurrent
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        $recurrentPayment = $this->chargeNow($recurrentPayment);

        // Check if next payment exists and there is no donation included
        $payment2 = $recurrentPayment->payment;
        $this->assertEquals($originalAmountWithoutDonation, $payment2->amount);
        $this->assertNull($payment2->additional_type);
        $this->verifyPaymentItemsTypesAndAmounts($payment2, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
        ]);

        // Charge again
        $recurrentPayment2 = $this->recurrentPaymentsRepository->recurrent($payment2);
        $recurrentPayment2 = $this->chargeNow($recurrentPayment2);

        // Check again
        $payment3 = $recurrentPayment2->payment;
        $this->assertEquals($originalAmountWithoutDonation, $payment3->amount);
        $this->assertNull($payment2->additional_type);
        $this->verifyPaymentItemsTypesAndAmounts($payment2, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
        ]);
    }

    public function testRecurrentDonation(): void
    {
        $st = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($st))
            ->addItem(new DonationPaymentItem('donation', 66, 0));

        $originalAmount = $paymentItemContainer->totalPrice();

        $payment = $this->paymentsRepository->add(
            subscriptionType: $st,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
            additionalAmount: 66,
            additionalType: 'recurrent'
        );

        // Make manual payment
        $this->paymentsProcessor->complete($payment, fn() => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $payment->status);
        $this->verifyPaymentItemsTypesAndAmounts($payment, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
            ['type' => DonationPaymentItem::TYPE, 'amount' => 66],
        ]);

        // Charge
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);
        $recurrentPayment = $this->chargeNow($recurrentPayment);

        // Check if next payment exists and there is donation included
        $payment2 = $recurrentPayment->payment;
        $this->assertEquals($originalAmount, $payment2->amount);
        $this->assertEquals('recurrent', $payment2->additional_type);
        $this->verifyPaymentItemsTypesAndAmounts($payment2, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
            ['type' => DonationPaymentItem::TYPE, 'amount' => 66],
        ]);

        // Charge again
        $recurrentPayment2 = $this->recurrentPaymentsRepository->recurrent($payment2);
        $recurrentPayment2 = $this->chargeNow($recurrentPayment2);

        // Check again
        $payment3 = $recurrentPayment2->payment;
        $this->assertEquals($originalAmount, $payment3->amount);
        $this->assertEquals('recurrent', $payment2->additional_type);
        $this->verifyPaymentItemsTypesAndAmounts($payment2, [
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 20],
            ['type' => SubscriptionTypePaymentItem::TYPE, 'amount' => 80],
            ['type' => DonationPaymentItem::TYPE, 'amount' => 66],
        ]);
    }

    private function chargeNow($recurrentPayment): ActiveRow
    {
        // Update recurrent to charge at NOW
        $this->recurrentPaymentsRepository->update($recurrentPayment, ['charge_at' => new DateTime]);

        // Run charge command
        $returnCode = $this->recurrentPaymentsChargeCommand->run(new StringInput(''), new NullOutput());
        $this->assertEquals(Command::SUCCESS, $returnCode);

        return $this->recurrentPaymentsRepository->find($recurrentPayment->id); // reload
    }

    private function createSubscriptionType()
    {
        /** @var SubscriptionTypeBuilder $stb */
        $stb = $this->inject(SubscriptionTypeBuilder::class);
        return $stb->createNew()
            ->setName('Test subscription')
            ->setCode('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(100)
            ->setLength(10)
            ->addSubscriptionTypeItem('a', 20, 10)
            ->addSubscriptionTypeItem('b', 80, 10)
            ->save();
    }

    private function verifyPaymentItemsTypesAndAmounts(ActiveRow $payment, array $items): void
    {
        $itemsToVerify = [];
        foreach ($this->paymentItemsRepository->getByPayment($payment) as $paymentItem) {
            $itemsToVerify[] = ['type' => $paymentItem->type, 'amount' => $paymentItem->amount];
        }
        $this->assertEqualsCanonicalizing($items, $itemsToVerify);
    }
}
