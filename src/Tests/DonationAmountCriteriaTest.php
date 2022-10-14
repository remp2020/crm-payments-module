<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Gateways\Paypal;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\DonationAmountCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repository\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class DonationAmountCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            PaymentsRepository::class,
            PaymentGatewaysRepository::class,
            PaymentItemsRepository::class,
            SubscriptionTypesRepository::class,
            UsersRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
            SubscriptionExtensionMethodsSeeder::class,
            SubscriptionLengthMethodSeeder::class,
            SubscriptionTypeNamesSeeder::class,
        ];
    }

    /**
     * @dataProvider dataProviderForTestDonationAmountCriteria
     */
    public function testDonationAmountCriteria($donatedAmount, $operator, $moreThenRule, $expectedValue)
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($donatedAmount);

        $criteria = $this->inject(DonationAmountCriteria::class);
        $values = (object)['selection' => $moreThenRule, 'operator' => $operator];
        $criteria->addConditions($paymentSelection, [DonationAmountCriteria::KEY => $values], $paymentRow);

        if ($expectedValue) {
            $this->assertNotNull($paymentSelection->fetch());
        } else {
            $this->assertNull($paymentSelection->fetch());
        }
    }


    public function dataProviderForTestDonationAmountCriteria(): array
    {
        return [
            [10, '>', 5, true],
            [12, '>', 14, false],
            [0, '>', 5, false],

            [5, '=', 0, false],
            [0, '=', 5, false],
            [5, '=', 5, true],
        ];
    }

    private function prepareData($donationAmount)
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

        $paymentItemContainer = new PaymentItemContainer();
        if ($donationAmount) {
            $paymentItemContainer->addItem(new DonationPaymentItem('Donation', (float) $donationAmount, 0));
        }

        /** @var PaymentsRepository $paymentsRepository */
        $paymentRow = $paymentsRepository->add(
            $subscriptionTypeRow,
            $paymentGatewayRow,
            $userRow,
            $paymentItemContainer,
            null,
            1,
            null,
            null,
            null,
            $donationAmount,
            null,
            null,
            null,
            false
        );

        $paymentSelection = $paymentsRepository->getTable()
            ->where(['payments.id' => $paymentRow->id]);

        return [$paymentSelection, $paymentRow];
    }
}
