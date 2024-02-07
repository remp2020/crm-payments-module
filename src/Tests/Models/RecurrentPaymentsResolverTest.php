<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Models;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Nette\Database\Table\ActiveRow;
use PHPUnit\Framework\Attributes\DataProvider;

class RecurrentPaymentsResolverTest extends PaymentsTestCase
{
    private RecurrentPaymentsResolver $recurrentPaymentsResolver;
    private SubscriptionTypesRepository $subscriptionTypesRepository;

    /** @var array<ActiveRow> */
    private array $subscriptionTypes;

    public function setUp(): void
    {
        parent::setUp();

        $this->recurrentPaymentsResolver = $this->inject(RecurrentPaymentsResolver::class);
        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }

    /* ***********************************************************************
     * ResolveSubscriptionType
     * ********************************************************************* */

    /*
     * If no next_subscription_type is set,
     *
     * => $recurrentPayment.subscription_type is returned.
    */
    public function testResolveSubscriptionTypeWithNoNextSubscriptionTypeSuccess()
    {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_test');
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);

        $this->assertEquals($subscriptionType->id, $resolvedSubscriptionType->id);
    }

    /*
     * If next_subscription_type is set in:
     * - $recurrent_payment.subscription_type.next_subscription_type,
     *
     * => $recurrent_payment.subscription_type.next_subscription_type is returned.
     */
    public function testResolveSubscriptionTypeWithNextSubscriptionTypeOnSubscriptionType()
    {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_1');
        $subscriptionTypeNextOnSubscriptionType = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_subscription_type_test');
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);

        // set SUBSCRIPTION_TYPE.next_subscription_type_id and trial period
        $this->subscriptionTypesRepository->update(
            $subscriptionType,
            [
                'next_subscription_type_id' => $subscriptionTypeNextOnSubscriptionType->id,
                'trial_periods' => 1,
            ],
        );

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        $this->assertEquals($subscriptionTypeNextOnSubscriptionType->id, $resolvedSubscriptionType->id);
    }

    /*
     * If next_subscription_type is set in:
     * - $recurrent_payment.next_subscription_type,
     *
     * => $recurrent_payment.next_subscription_type is returned.
     */
    public function testResolveSubscriptionTypeWithNextSubscriptionTypeOnRecurrentPayment()
    {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_test');
        $nextSubscriptionTypeOnRecurrentPayment = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_recurrent_payment');
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);

        // set SUBSCRIPTION_TYPE.next_subscription_type_id and trial period
        $this->recurrentPaymentsRepository->update(
            $recurrentPayment,
            ['next_subscription_type_id' => $nextSubscriptionTypeOnRecurrentPayment->id],
        );

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        $this->assertEquals($nextSubscriptionTypeOnRecurrentPayment->id, $resolvedSubscriptionType->id);
    }

    /*
     * If next_subscription_type is set in both:
     * - $recurrent_payment.next_subscription_type,
     * - $recurrent_payment.subscription_type.next_subscription_type
     *
     * => $recurrent_payment.next_subscription_type is returned.
     */
    public function testResolveSubscriptionTypeWithNextSubscriptionTypeOnSubscriptionTypeAndRecurrentPayment()
    {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_test');
        $nextSubscriptionTypeOnSubscriptionType = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_subscription_type_test');
        $nextSubscriptionTypeOnRecurrentPayment = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_recurrent_payment');
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);

        // set SUBSCRIPTION_TYPE.next_subscription_type_id and trial period
        $this->subscriptionTypesRepository->update(
            $subscriptionType,
            [
                'next_subscription_type_id' => $nextSubscriptionTypeOnSubscriptionType->id,
                'trial_periods' => 1,
            ],
        );
        // set RECURRENT_PAYMENT.next_subscription_type_id
        $this->recurrentPaymentsRepository->update(
            $recurrentPayment,
            ['next_subscription_type_id' => $nextSubscriptionTypeOnRecurrentPayment->id],
        );

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        $this->assertEquals($nextSubscriptionTypeOnRecurrentPayment->id, $resolvedSubscriptionType->id);
    }

    /*
     * If next_subscription_type is set in both:
     * - $recurrent_payment.next_subscription_type,
     * - $recurrent_payment.next_subscription_type.next_subscription_type
     *   - (Note: not on $recurrent_payment.subscription_type but on $recurrent_payment.next_subscription_type)
     *
     * => $recurrent_payment.next_subscription_type.next_subscription_type is returned.
     */
    public function testResolveSubscriptionTypeWithNextSubscriptionTypeOnRecurrentPaymentAndNextSubscriptionType()
    {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_test');
        $nextSubscriptionTypeOnRecurrentPayment = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_recurrent_payment');
        $nextSubscriptionTypeOnNextSubscriptionTypeOnRecurrentPayment = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_NEXT_subscription_type_ON_recurrent_payment');
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);

        // set RECURRENT_PAYMENT.next_subscription_type_id
        $this->recurrentPaymentsRepository->update(
            $recurrentPayment,
            ['next_subscription_type_id' => $nextSubscriptionTypeOnRecurrentPayment->id],
        );
        // set RECURRENT_PAYMENT.next_subscription_type.next_subscription_type_id
        $this->subscriptionTypesRepository->update(
            $nextSubscriptionTypeOnRecurrentPayment,
            [
                'next_subscription_type_id' => $nextSubscriptionTypeOnNextSubscriptionTypeOnRecurrentPayment->id,
                'trial_periods' => 1,
            ],
        );

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        $this->assertEquals($nextSubscriptionTypeOnNextSubscriptionTypeOnRecurrentPayment->id, $resolvedSubscriptionType->id);
    }

    #[DataProvider('dataProviderForTestMultipleSubscriptionsWithTrialPeriods')]
    public function testMultipleSubscriptionsWithTrialPeriods(
        int $existingRecurrentPayments,
        int $trialPeriods,
        bool $shouldReturnNextSubscriptionType,
    ) {
        $subscriptionType = $this->getSubscriptionTypeByCode('subscription_type_1');
        $nextSubscriptionType = $this->getSubscriptionTypeByCode('NEXT_subscription_type_ON_subscription_type_test');

        // set trial periods
        if ($trialPeriods > 0) {
            // set SUBSCRIPTION_TYPE.next_subscription_type_id and trial period
            $this->subscriptionTypesRepository->update(
                $subscriptionType,
                [
                    'next_subscription_type_id' => $nextSubscriptionType->id,
                    'trial_periods' => $trialPeriods,
                ],
            );
        }

        // create recurrents (one (current) has to exist)
        $recurrentPayment = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);
        if ($existingRecurrentPayments > 1) {
            $cnt = $existingRecurrentPayments - 1;
            $currentRecurrentPayment = $recurrentPayment;
            while ($cnt > 0) {
                $rpTmp = $this->createRecurrentPaymentWithSubscriptionType($subscriptionType);
                $this->recurrentPaymentsRepository->update($rpTmp, [
                    'charge_at' => $rpTmp->charge_at->modify('-' . $cnt . ' months'), // move to past
                    'state' => 'charged',
                    'payment_id' => $currentRecurrentPayment->parent_payment_id,
                ]);
                $cnt--;
                $currentRecurrentPayment = $rpTmp;
            }
        }
        // verify
        $this->assertEquals($existingRecurrentPayments, $this->recurrentPaymentsRepository->all()->count());

        $resolvedSubscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        if ($shouldReturnNextSubscriptionType) {
            $this->assertEquals($nextSubscriptionType->id, $resolvedSubscriptionType->id);
        } else {
            $this->assertEquals($subscriptionType->id, $resolvedSubscriptionType->id);
        }
    }

    public static function dataProviderForTestMultipleSubscriptionsWithTrialPeriods(): array
    {
        return [
            // 0 trial periods always ends with current subscription type
            '0trial_1payment' => [
                'existingRecurrentPayments' => 1,
                'trialPeriods' => 0,
                'shouldReturnNextSubscriptionType' => false,
            ],
            '0trial_2payments' => [
                'existingRecurrentPayments' => 2,
                'trialPeriods' => 0,
                'shouldReturnNextSubscriptionType' => false,
            ],

            // if number of existing payments is same as trial periods (or exceeds it)
            // next subscription type is returned
            '1trial_1used' => [
                'existingRecurrentPayments' => 1,
                'trialPeriods' => 1,
                'shouldReturnNextSubscriptionType' => true,
            ],
            '2trials_3used' => [
                'existingRecurrentPayments' => 3,
                'trialPeriods' => 2,
                'shouldReturnNextSubscriptionType' => true,
            ],
            '3trials_3used' => [
                'existingRecurrentPayments' => 3,
                'trialPeriods' => 3,
                'shouldReturnNextSubscriptionType' => true,
            ],

            // current recurrent payment creates second USE of trial
            // resolver of subscription type for next recurrent payment should return next subscription type
            '2trials_1used' => [
                'existingRecurrentPayments' => 1,
                'trialPeriods' => 2,
                'shouldReturnNextSubscriptionType' => false,
            ],

            // current recurrent payment creates third USE of trial
            // trials were not used yet
            '4trials_2used' => [
                'existingRecurrentPayments' => 2,
                'trialPeriods' => 4,
                'shouldReturnNextSubscriptionType' => false,
            ],
        ];
    }

    /* ***********************************************************************
     * Helper methods
     * ********************************************************************* */

    protected function getSubscriptionTypeByCode(string $code): ActiveRow
    {
        if (!isset($this->subscriptionTypes[$code])) {
            /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
            $subscriptionTypeBuilder = $this->container->getByType(SubscriptionTypeBuilder::class);
            $this->subscriptionTypes[$code] = $subscriptionTypeBuilder->createNew()
                ->setName($code)
                ->setUserLabel($code)
                ->setPrice(1.99)
                ->addSubscriptionTypeItem('first item', 1, 20)
                ->addSubscriptionTypeItem('second item', 0.99, 20)
                ->setCode($code)
                ->setLength(31)
                ->setActive(true)
                ->save();
        }
        return $this->subscriptionTypes[$code];
    }

    private function createRecurrentPaymentWithSubscriptionType(ActiveRow $subscriptionType)
    {
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));
        $payment = $this->paymentsRepository->add($subscriptionType, $this->getPaymentGateway(), $this->getUser(), $paymentItemContainer);
        $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID);

        return $this->recurrentPaymentsRepository->add(
            'CID',
            $payment,
            (new \DateTime())->modify('+1 months'),
            null,
            4
        );
    }
}
