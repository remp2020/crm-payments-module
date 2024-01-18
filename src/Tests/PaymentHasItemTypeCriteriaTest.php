<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApplicationModule\Tests\DatabaseTestCase;
use Crm\PaymentsModule\Gateways\Paypal;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentHasItemTypeCriteria;
use Crm\PaymentsModule\Seeders\PaymentGatewaysSeeder;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Seeders\SubscriptionExtensionMethodsSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionLengthMethodSeeder;
use Crm\SubscriptionsModule\Seeders\SubscriptionTypeNamesSeeder;
use Crm\UsersModule\Models\Builder\UserBuilder;
use Crm\UsersModule\Repositories\UsersRepository;
use PHPUnit\Framework\Attributes\DataProvider;

class PaymentHasItemTypeCriteriaTest extends DatabaseTestCase
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

    #[DataProvider('dataProvider')]
    public function testPaymentHasItemTypeCriteria($submittedItems, $checkedItems, $expectedValue)
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($submittedItems);

        $criteria = $this->inject(PaymentHasItemTypeCriteria::class);
        $values = (object)['selection' => $checkedItems];
        $criteria->addConditions($paymentSelection, [PaymentHasItemTypeCriteria::KEY => $values], $paymentRow);

        if ($expectedValue) {
            $this->assertNotNull($paymentSelection->fetch());
        } else {
            $this->assertNull($paymentSelection->fetch());
        }
    }


    public static function dataProvider(): array
    {
        return [
            'matchesAll' => [
                'submittedItems' => [SubscriptionTypePaymentItem::TYPE],
                'checkedItems' => [SubscriptionTypePaymentItem::TYPE],
                'expectedValue' => true,
            ],
            'matchesAll_submittedMore' => [
                'submittedItems' => [SubscriptionTypePaymentItem::TYPE, DonationPaymentItem::TYPE],
                'checkedItems' => [SubscriptionTypePaymentItem::TYPE],
                'expectedValue' => true,
            ],
            'matchesSome' => [
                'submittedItems' => [SubscriptionTypePaymentItem::TYPE],
                'checkedItems' => [SubscriptionTypePaymentItem::TYPE, DonationPaymentItem::TYPE],
                'expectedValue' => true,
            ],
            'matchesNone' => [
                'submittedItems' => [SubscriptionTypePaymentItem::TYPE],
                'checkedItems' => [DonationPaymentItem::TYPE],
                'expectedValue' => false,
            ],
        ];
    }

    private function paymentItem(string $type)
    {
        if ($type === SubscriptionTypePaymentItem::TYPE) {
            return new SubscriptionTypePaymentItem(
                $this->subscriptionType()->id,
                'subscription_type',
                10,
                10
            );
        }
        if ($type === DonationPaymentItem::TYPE) {
            return new DonationPaymentItem(
                'donation',
                10,
                0
            );
        }

        throw new \Exception('unexpected payment item type: ' . $type);
    }

    private $subscriptionTypeRow;
    private function subscriptionType()
    {
        if (!$this->subscriptionTypeRow) {
            /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
            $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
            $this->subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
                ->setNameAndUserLabel('test')
                ->setLength(31)
                ->setPrice(1)
                ->setActive(1)
                ->save();
        }
        return $this->subscriptionTypeRow;
    }

    private function prepareData($submittedItems)
    {
        /** @var UserBuilder $userBuilder */
        $userBuilder = $this->inject(UserBuilder::class);
        $userRow = $userBuilder->createNew()
            ->setEmail('test@test.sk')
            ->setPassword('secret', false)
            ->setPublicName('test@test.sk')
            ->save();

        /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
        $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
        $paymentGatewayRow = $paymentGatewaysRepository->findBy('code', Paypal::GATEWAY_CODE);

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $paymentItemContainer = new PaymentItemContainer();
        foreach ($submittedItems as $submittedItem) {
            $paymentItemContainer->addItem($this->paymentItem($submittedItem));
        }

        /** @var PaymentsRepository $paymentsRepository */
        $paymentRow = $paymentsRepository->add(
            $this->subscriptionType(),
            $paymentGatewayRow,
            $userRow,
            $paymentItemContainer,
            null,
            1,
            null,
            null,
            null,
            0,
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
