<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Scenarios\PaymentHasSubscriptionCriteria;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use PHPUnit\Framework\Attributes\DataProvider;

class PaymentHasSubscriptionCriteriaTest extends PaymentsTestCase
{
    public function requiredRepositories(): array
    {
        return array_merge(parent::requiredRepositories(), [
            SubscriptionsRepository::class,
            SubscriptionTypesRepository::class,
        ]);
    }

    public static function dataProviderForTestPaymentHasSubscriptionCriteria(): array
    {
        return [
            [true, true, true],
            [true, false, false],
            [false, true, false],
            [false, false, true],
        ];
    }

    #[DataProvider('dataProviderForTestPaymentHasSubscriptionCriteria')]
    public function testPaymentHasSubscriptionCriteria($hasSubscription, $shoudHaveSubscription, $expectedResult)
    {
        [$paymentSelection, $paymentRow] = $this->prepareData($hasSubscription);

        /** @var PaymentHasSubscriptionCriteria $criteria $criteria */
        $criteria = $this->inject(PaymentHasSubscriptionCriteria::class);
        $values = (object)['selection' => $shoudHaveSubscription];
        $criteria->addConditions($paymentSelection, [PaymentHasSubscriptionCriteria::KEY => $values], $paymentRow);

        if ($expectedResult) {
            $this->assertNotNull($paymentSelection->fetch());
        } else {
            $this->assertNull($paymentSelection->fetch());
        }
    }

    private function prepareData(bool $withSubscription)
    {
        $user = $this->getUser();

        /** @var PaymentsRepository $paymentsRepository */
        $paymentsRepository = $this->getRepository(PaymentsRepository::class);

        $subscriptionTypeRow = null;
        if ($withSubscription) {
            /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
            $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
            $subscriptionTypeRow = $subscriptionTypeBuilder->createNew()
                ->setNameAndUserLabel('test')
                ->setLength(31)
                ->setPrice(1)
                ->setActive(1)
                ->save();
        }

        /** @var PaymentsRepository $paymentsRepository */
        $paymentRow = $paymentsRepository->add(
            $subscriptionTypeRow,
            $this->getPaymentGateway(),
            $user,
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
            false
        );

        if ($withSubscription) {
            $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
            $subscriptionRow = $subscriptionsRepository->add(
                $subscriptionTypeRow,
                true,
                true,
                $user
            );
            $this->paymentsRepository->addSubscriptionToPayment($subscriptionRow, $paymentRow);
        }

        $paymentSelection = $paymentsRepository->getTable()
            ->where(['payments.id' => $paymentRow->id]);

        return [$paymentSelection, $paymentRow];
    }
}
