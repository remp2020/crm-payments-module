<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Models\Gateways\Paypal;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentIsRecurrentChargeCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Seeders\ContentAccessSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Repositories\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;

class PaymentIsRecurrentChargeCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
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

    #[DataProvider('dataProviderForTestIsRecurrentCharge')]
    public function testIsRecurrentCharge(bool $isRecurrentCharge, bool $selectedValue, bool $expectedValue): void
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($isRecurrentCharge);

        $criteria = new PaymentIsRecurrentChargeCriteria();
        $values = (object)['selection' => $selectedValue];
        $criteria->addConditions($paymentSelection, [PaymentIsRecurrentChargeCriteria::KEY => $values], $paymentRow);

        if ($expectedValue) {
            $this->assertNotNull($paymentSelection->fetch());
        } else {
            $this->assertNull($paymentSelection->fetch());
        }
    }

    public static function dataProviderForTestIsRecurrentCharge(): array
    {
        return [
            [true, true, true],
            [true, false, false],
            [false, true, false],
            [false, false, true],
        ];
    }

    private function prepareData(bool $isRecurrentCharge): array
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

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', Paypal::GATEWAY_CODE);

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        /** @var PaymentsRepository $paymentsRepository */
        $paymentRow = $paymentsRepository->add(
            $subscriptionTypeRow,
            $paymentGatewayRow,
            $userRow,
            new PaymentItemContainer(),
            null,
            1,
            null,
            null,
            null,
            0,
            null,
            null,
            null,
            $isRecurrentCharge
        );

        $paymentSelection = $paymentsRepository->getTable()
            ->where(['payments.id' => $paymentRow->id]);

        return [$paymentSelection, $paymentRow];
    }
}
