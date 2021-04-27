<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\DonationAmountCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Builder\SubscriptionTypeBuilder;
use Crm\UsersModule\Auth\UserManager;
use Crm\UsersModule\Repository\UsersRepository;

class DonationAmountCriteriaTest extends DatabaseTestCase
{
    protected function requiredRepositories(): array
    {
        return [
            PaymentsRepository::class,
            PaymentItemsRepository::class,
            UsersRepository::class,
            PaymentGatewaysRepository::class,
        ];
    }

    protected function requiredSeeders(): array
    {
        return [
            PaymentGatewaysSeeder::class,
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
            $this->assertNotFalse($paymentSelection->fetch());
        } else {
            $this->assertFalse($paymentSelection->fetch());
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
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', 'paypal');


        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $paymentItemContainer = new PaymentItemContainer();
        if ($donationAmount) {
            $paymentItemContainer->addItem(new DonationPaymentItem('Donation', $donationAmount, 0));
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
