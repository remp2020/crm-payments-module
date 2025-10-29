<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Recurrent;

use Crm\PaymentsModule\Commands\RecurrentPaymentsChargeCommand;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Models\RecurrentPayment\ChainData;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\PaymentMethodsRepository;
use Crm\PaymentsModule\Seeders\TestPaymentGatewaysSeeder;
use Crm\PaymentsModule\Tests\Gateways\TestRecurrentGateway;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;

class ChainTrackingTest extends PaymentsTestCase
{
    private PaymentProcessor $paymentsProcessor;
    private RecurrentPaymentsChargeCommand $recurrentPaymentsChargeCommand;
    protected PaymentMethodsRepository $paymentMethodsRepository;
    private ActiveRow $user;

    public function setUp(): void
    {
        $this->refreshContainer();
        parent::setUp();
        $this->paymentsProcessor = $this->inject(PaymentProcessor::class);
        $this->recurrentPaymentsChargeCommand = $this->inject(RecurrentPaymentsChargeCommand::class);
        $this->paymentMethodsRepository = $this->inject(PaymentMethodsRepository::class);

        $userManager = $this->inject(UserManager::class);
        $this->user = $userManager->addNewUser('user@example.com', false);

        $this->recurrentPaymentsChargeCommand->setFastChargeThreshold(0);
    }

    public function requiredSeeders(): array
    {
        return [
            ...parent::requiredSeeders(),
            TestPaymentGatewaysSeeder::class,
        ];
    }

    public function testNewChainGeneration(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        // Complete the payment to trigger recurrent payment creation
        $this->paymentsProcessor->complete($payment, fn () => null);
        $payment = $this->paymentsRepository->find($payment->id);

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        // Verify new chain was created
        $this->assertNotEmpty($recurrentPayment->chain_id);
        $this->assertEquals(12, strlen($recurrentPayment->chain_id));
        $this->assertEquals(1, $recurrentPayment->cycle);
    }

    public function testCycleIncrementOnSuccessfulCharge(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        // Create initial payment and recurrent
        $payment1 = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        $this->paymentsProcessor->complete($payment1, fn () => null);
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $recurrentPayment1 = $this->recurrentPaymentsRepository->recurrent($payment1);

        // Charge the recurrent payment to create next one
        $recurrentPayment1 = $this->chargeNow($recurrentPayment1);
        $payment2 = $recurrentPayment1->payment;
        $recurrentPayment2 = $this->recurrentPaymentsRepository->recurrent($payment2);

        // Verify chain continuity and cycle increment
        $this->assertEquals($recurrentPayment1->chain_id, $recurrentPayment2->chain_id);
        $this->assertEquals($recurrentPayment1->cycle + 1, $recurrentPayment2->cycle);
        $this->assertEquals(2, $recurrentPayment2->cycle);
    }

    public function testCycleMaintainedOnFailedRetry(): void
    {
        $subscriptionType = $this->createSubscriptionType();

        // Create a payment method for manual recurrent payment creation
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $this->user->id,
            $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE)->id,
            'test_token',
        );

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: (new PaymentItemContainer())
                ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType)),
        );

        // Create initial recurrent payment with known chain (scheduled first)
        $initialRecurrent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            new DateTime('+1 day'),
            null,
            2,
            chainId: 'test_chain',
            cycle: 3,
        );

        // Simulate failed retry scheduled after initial (same chain_id and cycle maintained)
        $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            new DateTime('+2 days'),
            null,
            1,
            chainId: $initialRecurrent->chain_id,
            cycle: $initialRecurrent->cycle,
        );

        $retryRecurrent = $this->recurrentPaymentsRepository->getTable()
            ->where('parent_payment_id', $payment->id)
            ->where('retries', 1)
            ->fetch();

        // Verify retry maintains same chain_id and cycle
        $this->assertEquals('test_chain', $retryRecurrent->chain_id);
        $this->assertEquals(3, $retryRecurrent->cycle);
    }

    public function testMultipleRetriesThenSuccess(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        // Create initial payment and recurrent
        $payment1 = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        $this->paymentsProcessor->complete($payment1, fn () => null);
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $recurrentPayment1 = $this->recurrentPaymentsRepository->recurrent($payment1);

        $originalChainId = $recurrentPayment1->chain_id;
        $originalCycle = $recurrentPayment1->cycle;

        // Simulate 2 failed retries (cycle should stay the same)
        $paymentMethod = $recurrentPayment1->payment_method;

        $retry1 = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment1,
            new DateTime('+1 day'),
            null,
            1,
            chainId: $originalChainId,
            cycle: $originalCycle,
        );

        $retry2 = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment1,
            new DateTime('+2 days'),
            null,
            0,
            chainId: $originalChainId,
            cycle: $originalCycle,
        );

        // Verify retries maintain same cycle
        $this->assertEquals($originalChainId, $retry1->chain_id);
        $this->assertEquals($originalCycle, $retry1->cycle);
        $this->assertEquals($originalChainId, $retry2->chain_id);
        $this->assertEquals($originalCycle, $retry2->cycle);

        // Now simulate successful charge (should increment cycle)
        $this->recurrentPaymentsRepository->setCharged($retry2, $payment1, 'OK', 'Success');
        $retry2 = $this->recurrentPaymentsRepository->find($retry2->id);

        // Create next payment in chain
        $payment2 = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        // Update retry2's payment_id to link to the new payment BEFORE completing
        $this->recurrentPaymentsRepository->update($retry2, ['payment_id' => $payment2->id]);

        // Update payment status directly (without processor to avoid auto-creating recurrent payment)
        $this->paymentsRepository->update($payment2, [
            'status' => PaymentStatusEnum::Paid->value,
            'paid_at' => new DateTime(),
        ]);
        $payment2 = $this->paymentsRepository->find($payment2->id);

        $recurrentPayment2 = $this->recurrentPaymentsRepository->createFromPayment(
            $payment2,
            'test_token',
        );

        // Verify cycle incremented after success
        $this->assertEquals($originalChainId, $recurrentPayment2->chain_id);
        $this->assertEquals($originalCycle + 1, $recurrentPayment2->cycle);
    }

    public function testChainContinuityAcrossMultipleRenewals(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        // Create initial payment and recurrent
        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        $this->paymentsProcessor->complete($payment, fn () => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        $originalChainId = $recurrentPayment->chain_id;
        $chain = [$recurrentPayment];

        // Create 5 successful renewals
        for ($i = 0; $i < 5; $i++) {
            $recurrentPayment = $this->chargeNow($recurrentPayment);
            $payment = $recurrentPayment->payment;
            $nextRecurrent = $this->recurrentPaymentsRepository->recurrent($payment);
            $chain[] = $nextRecurrent;
            $recurrentPayment = $nextRecurrent;
        }

        // Verify all payments in chain have same chain_id and incrementing cycles
        for ($i = 0; $i < count($chain); $i++) {
            $this->assertEquals($originalChainId, $chain[$i]->chain_id);
            $this->assertEquals($i + 1, $chain[$i]->cycle);
        }
    }

    public function testParentStateIncrementsCycle(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $this->user->id,
            $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE)->id,
            'test_token',
        );

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: (new PaymentItemContainer())
                ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType)),
        );

        // Test states that should increment cycle
        $incrementingStates = [
            RecurrentPaymentStateEnum::Charged->value,
            RecurrentPaymentStateEnum::Active->value,
            RecurrentPaymentStateEnum::Pending->value,
        ];

        foreach ($incrementingStates as $state) {
            $parentRecurrent = $this->recurrentPaymentsRepository->add(
                $paymentMethod,
                $payment,
                new DateTime('+1 day'),
                null,
                1,
                chainId: 'test_chain',
                cycle: 2,
            );

            // Update to test state
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['state' => $state]);
            $parentRecurrent = $this->recurrentPaymentsRepository->find($parentRecurrent->id);

            // Test ChainData directly
            $chainData = ChainData::fromParentPayment($parentRecurrent);
            $this->assertEquals('test_chain', $chainData->chainId);
            $this->assertEquals(3, $chainData->cycle, "ChainData should increment cycle for state {$state}");

            // Create payment with this parent
            $childPayment = $this->paymentsRepository->add(
                subscriptionType: $subscriptionType,
                paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
                user: $this->user,
                paymentItemContainer: (new PaymentItemContainer())
                    ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType)),
            );

            // Link the parent recurrent to this new payment
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['payment_id' => $childPayment->id]);

            // Update payment status to paid
            $this->paymentsRepository->update($childPayment, [
                'status' => PaymentStatusEnum::Paid->value,
                'paid_at' => new DateTime(),
            ]);
            $childPayment = $this->paymentsRepository->find($childPayment->id);

            $childRecurrent = $this->recurrentPaymentsRepository->createFromPayment($childPayment, 'test_token');

            // Should increment cycle
            $this->assertEquals('test_chain', $childRecurrent->chain_id);
            $this->assertEquals(3, $childRecurrent->cycle, "State {$state} should increment cycle");
        }
    }

    public function testParentStateDoesNotIncrementCycle(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $this->user->id,
            $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE)->id,
            'test_token',
        );

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: (new PaymentItemContainer())
                ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType)),
        );

        // Test states that should NOT increment cycle
        $nonIncrementingStates = [
            RecurrentPaymentStateEnum::ChargeFailed->value,
            RecurrentPaymentStateEnum::SystemStop->value,
            RecurrentPaymentStateEnum::UserStop->value,
        ];

        foreach ($nonIncrementingStates as $state) {
            $parentRecurrent = $this->recurrentPaymentsRepository->add(
                $paymentMethod,
                $payment,
                new DateTime('+1 day'),
                null,
                1,
                chainId: 'test_chain',
                cycle: 2,
            );

            // Update to test state
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['state' => $state]);
            $parentRecurrent = $this->recurrentPaymentsRepository->find($parentRecurrent->id);

            // Test ChainData directly
            $chainData = ChainData::fromParentPayment($parentRecurrent);
            $this->assertEquals('test_chain', $chainData->chainId);
            $this->assertEquals(2, $chainData->cycle, "ChainData should NOT increment cycle for state {$state}");

            // Create payment with this parent
            $childPayment = $this->paymentsRepository->add(
                subscriptionType: $subscriptionType,
                paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
                user: $this->user,
                paymentItemContainer: (new PaymentItemContainer())
                    ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType)),
            );

            // Link the parent recurrent to this new payment
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['payment_id' => $childPayment->id]);

            // Update payment status to paid
            $this->paymentsRepository->update($childPayment, [
                'status' => PaymentStatusEnum::Paid->value,
                'paid_at' => new DateTime(),
            ]);
            $childPayment = $this->paymentsRepository->find($childPayment->id);

            $childRecurrent = $this->recurrentPaymentsRepository->createFromPayment($childPayment, 'test_token');

            // Should NOT increment cycle
            $this->assertEquals('test_chain', $childRecurrent->chain_id);
            $this->assertEquals(2, $childRecurrent->cycle, "State {$state} should NOT increment cycle");
        }
    }

    public function testOrphanedPaymentCreatesNewChain(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        // Create a payment without parent recurrent (orphaned scenario)
        $orphanPayment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );

        $this->paymentsProcessor->complete($orphanPayment, fn () => null);
        $orphanPayment = $this->paymentsRepository->find($orphanPayment->id);

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($orphanPayment);

        // Should create new chain since parent recurrent doesn't exist
        $this->assertNotEmpty($recurrentPayment->chain_id);
        $this->assertEquals(12, strlen($recurrentPayment->chain_id));
        $this->assertEquals(1, $recurrentPayment->cycle);
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
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);
        return $subscriptionTypeBuilder->createNew()
            ->setName('Test subscription')
            ->setCode('test_subscription')
            ->setUserLabel('')
            ->setActive(true)
            ->setPrice(100)
            ->setLength(30)
            ->save();
    }
}
