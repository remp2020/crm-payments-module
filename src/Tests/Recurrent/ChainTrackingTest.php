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
        $payment = $this->createPaymentForSubscription();

        $this->paymentsProcessor->complete($payment, fn () => null);
        $payment = $this->paymentsRepository->find($payment->id);

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        $this->assertNotEmpty($recurrentPayment->chain_id);
        $this->assertEquals(12, strlen($recurrentPayment->chain_id));
        $this->assertEquals(1, $recurrentPayment->cycle);
    }

    public function testCycleIncrementOnSuccessfulCharge(): void
    {
        $payment1 = $this->createPaymentForSubscription();

        $this->paymentsProcessor->complete($payment1, fn () => null);
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $recurrentPayment1 = $this->recurrentPaymentsRepository->recurrent($payment1);

        $recurrentPayment1 = $this->chargeNow($recurrentPayment1);
        $payment2 = $recurrentPayment1->payment;
        $recurrentPayment2 = $this->recurrentPaymentsRepository->recurrent($payment2);

        $this->assertEquals($recurrentPayment1->chain_id, $recurrentPayment2->chain_id);
        $this->assertEquals($recurrentPayment1->cycle + 1, $recurrentPayment2->cycle);
        $this->assertEquals(2, $recurrentPayment2->cycle);
    }

    public function testCycleMaintainedOnFailedRetry(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->createTestPaymentMethod();
        $payment = $this->createPaymentForSubscription($subscriptionType);

        $initialRecurrent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            new DateTime('+1 day'),
            null,
            2,
            chainId: 'test_chain',
            cycle: 3,
        );

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

        $this->assertEquals('test_chain', $retryRecurrent->chain_id);
        $this->assertEquals(3, $retryRecurrent->cycle);
    }

    public function testMultipleRetriesThenSuccess(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $payment1 = $this->createPaymentForSubscription($subscriptionType);

        $this->paymentsProcessor->complete($payment1, fn () => null);
        $payment1 = $this->paymentsRepository->find($payment1->id);
        $recurrentPayment1 = $this->recurrentPaymentsRepository->recurrent($payment1);

        $originalChainId = $recurrentPayment1->chain_id;
        $originalCycle = $recurrentPayment1->cycle;
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

        $this->assertEquals($originalChainId, $retry1->chain_id);
        $this->assertEquals($originalCycle, $retry1->cycle);
        $this->assertEquals($originalChainId, $retry2->chain_id);
        $this->assertEquals($originalCycle, $retry2->cycle);

        $this->recurrentPaymentsRepository->setCharged($retry2, $payment1, 'OK', 'Success');
        $retry2 = $this->recurrentPaymentsRepository->find($retry2->id);

        $payment2 = $this->createPaymentForSubscription($subscriptionType);
        $this->recurrentPaymentsRepository->update($retry2, ['payment_id' => $payment2->id]);
        $payment2 = $this->markPaymentAsPaid($payment2);

        $recurrentPayment2 = $this->recurrentPaymentsRepository->createFromPayment(
            $payment2,
            'test_token',
        );

        $this->assertEquals($originalChainId, $recurrentPayment2->chain_id);
        $this->assertEquals($originalCycle + 1, $recurrentPayment2->cycle);
    }

    public function testChainContinuityAcrossMultipleRenewals(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $payment = $this->createPaymentForSubscription($subscriptionType);

        $this->paymentsProcessor->complete($payment, fn () => null);
        $payment = $this->paymentsRepository->find($payment->id);
        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment);

        $originalChainId = $recurrentPayment->chain_id;
        $chain = [$recurrentPayment];

        for ($i = 0; $i < 5; $i++) {
            $recurrentPayment = $this->chargeNow($recurrentPayment);
            $payment = $recurrentPayment->payment;
            $nextRecurrent = $this->recurrentPaymentsRepository->recurrent($payment);
            $chain[] = $nextRecurrent;
            $recurrentPayment = $nextRecurrent;
        }

        for ($i = 0; $i < count($chain); $i++) {
            $this->assertEquals($originalChainId, $chain[$i]->chain_id);
            $this->assertEquals($i + 1, $chain[$i]->cycle);
        }
    }

    public function testParentStateIncrementsCycle(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->createTestPaymentMethod();
        $payment = $this->createPaymentForSubscription($subscriptionType);

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

            $this->recurrentPaymentsRepository->update($parentRecurrent, ['state' => $state]);
            $parentRecurrent = $this->recurrentPaymentsRepository->find($parentRecurrent->id);

            $childPayment = $this->createPaymentForSubscription($subscriptionType);
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['payment_id' => $childPayment->id]);
            $childPayment = $this->markPaymentAsPaid($childPayment);

            $childRecurrent = $this->recurrentPaymentsRepository->createFromPayment($childPayment, 'test_token');

            $this->assertEquals('test_chain', $childRecurrent->chain_id);
            $this->assertEquals(3, $childRecurrent->cycle, "State {$state} should increment cycle");
        }
    }

    public function testParentStateDoesNotIncrementCycle(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->createTestPaymentMethod();
        $payment = $this->createPaymentForSubscription($subscriptionType);

        $nonIncrementingStates = [
            RecurrentPaymentStateEnum::AdminStop->value,
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

            $this->recurrentPaymentsRepository->update($parentRecurrent, ['state' => $state]);
            $parentRecurrent = $this->recurrentPaymentsRepository->find($parentRecurrent->id);

            $childPayment = $this->createPaymentForSubscription($subscriptionType);
            $this->recurrentPaymentsRepository->update($parentRecurrent, ['payment_id' => $childPayment->id]);
            $childPayment = $this->markPaymentAsPaid($childPayment);

            $childRecurrent = $this->recurrentPaymentsRepository->createFromPayment($childPayment, 'test_token');

            $this->assertEquals('test_chain', $childRecurrent->chain_id);
            $this->assertEquals(2, $childRecurrent->cycle, "State {$state} should NOT increment cycle");
        }
    }

    public function testOrphanedPaymentCreatesNewChain(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $orphanPayment = $this->createPaymentForSubscription($subscriptionType);

        $this->paymentsProcessor->complete($orphanPayment, fn () => null);
        $orphanPayment = $this->paymentsRepository->find($orphanPayment->id);

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($orphanPayment);

        $this->assertNotEmpty($recurrentPayment->chain_id);
        $this->assertEquals(12, strlen($recurrentPayment->chain_id));
        $this->assertEquals(1, $recurrentPayment->cycle);
    }

    public function testLegacyRecurrentWithoutChainId(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->createTestPaymentMethod();
        $legacyPayment = $this->createPaymentForSubscription($subscriptionType);

        $legacyRecurrent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $legacyPayment,
            new DateTime('+1 day'),
            null,
            1,
        );

        $this->assertNull($legacyRecurrent->chain_id);
        $this->assertNull($legacyRecurrent->cycle);

        $chainData = ChainData::fromParentPayment($legacyRecurrent);

        // "both or neither" rule: ChainData returns null for both fields when parent has no chain tracking
        $this->assertNull($chainData->chainId, 'ChainData should return null chainId for legacy parent');
        $this->assertNull($chainData->cycle, 'ChainData should return null cycle for legacy parent');

        $newPayment = $this->createPaymentForSubscription($subscriptionType);
        $this->recurrentPaymentsRepository->update($legacyRecurrent, ['payment_id' => $newPayment->id]);
        $newPayment = $this->markPaymentAsPaid($newPayment);

        $newRecurrent = $this->recurrentPaymentsRepository->createFromPayment($newPayment, 'test_token_2');

        // Forward compatibility: Children inherit NULL tracking to prevent inconsistent data until backfill
        $this->assertNull($newRecurrent->chain_id, 'Child of legacy parent should have null chain_id');
        $this->assertNull($newRecurrent->cycle, 'Child of legacy parent should have null cycle');
    }

    public function testReactivateSystemStoppedPreservesChain(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $payment1 = $this->createPaymentForSubscription($subscriptionType);

        $this->paymentsProcessor->complete($payment1, fn () => null);
        $payment1 = $this->paymentsRepository->find($payment1->id);

        $recurrentPayment = $this->recurrentPaymentsRepository->recurrent($payment1);
        $originalChainId = $recurrentPayment->chain_id;
        $originalCycle = $recurrentPayment->cycle;

        $this->assertNotNull($originalChainId);
        $this->assertEquals(1, $originalCycle);

        $chargedRecurrent = $this->chargeNow($recurrentPayment);
        $payment2 = $chargedRecurrent->payment;
        $this->assertNotNull($payment2, 'Charged recurrent should have payment_id');

        // Reactivation prerequisite: retries must be 0
        $this->recurrentPaymentsRepository->update($chargedRecurrent, [
            'state' => RecurrentPaymentStateEnum::SystemStop->value,
            'retries' => 0,
        ]);
        $chargedRecurrent = $this->recurrentPaymentsRepository->find($chargedRecurrent->id);

        $reactivatedRecurrent = $this->recurrentPaymentsRepository->reactivateSystemStopped($chargedRecurrent);

        $this->assertNotNull($reactivatedRecurrent);
        $this->assertEquals($originalChainId, $reactivatedRecurrent->chain_id, 'Reactivation should preserve chain_id');
        $this->assertEquals($originalCycle, $reactivatedRecurrent->cycle, 'Reactivation should preserve cycle');
        $this->assertEquals(
            RecurrentPaymentStateEnum::Active->value,
            $reactivatedRecurrent->state,
            'Reactivated recurrent should be Active',
        );
    }

    public function testFastChargeBlockedWithinSameChain(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $paymentMethod = $this->createTestPaymentMethod();

        // recently charged parent in the chain
        $parentPayment = $this->createPaymentForSubscription($subscriptionType);
        $parent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $parentPayment,
            new DateTime(),
            null,
            1,
            chainId: 'chain_a',
            cycle: 1,
        );
        $this->recurrentPaymentsRepository->update($parent, [
            'state' => RecurrentPaymentStateEnum::Charged->value,
            'charge_at' => new DateTime(),
            'payment_id' => $parentPayment->id,
        ]);

        // chargeable recurrent in the SAME chain, due now
        $payment = $this->createPaymentForSubscription($subscriptionType);
        $recurrent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            new DateTime(),
            null,
            1,
            chainId: 'chain_a',
            cycle: 2,
        );

        $this->recurrentPaymentsChargeCommand->setFastChargeThreshold(24);
        $this->assertEquals(
            Command::SUCCESS,
            $this->recurrentPaymentsChargeCommand->run(new StringInput(''), new NullOutput()),
        );

        $recurrent = $this->recurrentPaymentsRepository->find($recurrent->id);
        $this->assertEquals(RecurrentPaymentStateEnum::SystemStop->value, $recurrent->state);
        $this->assertEquals('Fast charge', $recurrent->note);
    }

    public function testFastChargeAllowedAcrossDifferentChains(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        // same payment method (token) shared by both chains
        $paymentMethod = $this->createTestPaymentMethod();

        // recently charged parent in chain A
        $parentPayment = $this->createPaymentForSubscription($subscriptionType);
        $parent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $parentPayment,
            new DateTime(),
            null,
            1,
            chainId: 'chain_a',
            cycle: 1,
        );
        $this->recurrentPaymentsRepository->update($parent, [
            'state' => RecurrentPaymentStateEnum::Charged->value,
            'charge_at' => new DateTime(),
            'payment_id' => $parentPayment->id,
        ]);

        // chargeable recurrent in a DIFFERENT chain, due now
        $payment = $this->createPaymentForSubscription($subscriptionType);
        $recurrent = $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            new DateTime(),
            null,
            1,
            chainId: 'chain_b',
            cycle: 1,
        );

        $this->recurrentPaymentsChargeCommand->setFastChargeThreshold(24);
        $this->assertEquals(
            Command::SUCCESS,
            $this->recurrentPaymentsChargeCommand->run(new StringInput(''), new NullOutput()),
        );

        // different chain => not fast charge => charged normally
        $recurrent = $this->recurrentPaymentsRepository->find($recurrent->id);
        $this->assertNotEquals('Fast charge', $recurrent->note);
        $this->assertEquals(RecurrentPaymentStateEnum::Charged->value, $recurrent->state);
    }

    private function chargeNow(ActiveRow $recurrentPayment): ActiveRow
    {
        // Execution order critical: charge_at must be NOW before command runs
        $this->recurrentPaymentsRepository->update($recurrentPayment, ['charge_at' => new DateTime]);

        // Use command directly (not processor) to test real recurrent payment flow
        $returnCode = $this->recurrentPaymentsChargeCommand->run(new StringInput(''), new NullOutput());
        $this->assertEquals(Command::SUCCESS, $returnCode);

        return $this->recurrentPaymentsRepository->find($recurrentPayment->id);
    }

    private function createSubscriptionType(): ActiveRow
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

    private function createPaymentForSubscription(?ActiveRow $subscriptionType = null): ActiveRow
    {
        $subscriptionType ??= $this->createSubscriptionType();
        $paymentItemContainer = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType));

        return $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE),
            user: $this->user,
            paymentItemContainer: $paymentItemContainer,
        );
    }

    private function createTestPaymentMethod(string $token = 'test_token'): ActiveRow
    {
        return $this->paymentMethodsRepository->findOrAdd(
            $this->user->id,
            $this->paymentGatewaysRepository->findByCode(TestRecurrentGateway::GATEWAY_CODE)->id,
            $token,
        );
    }

    private function markPaymentAsPaid(ActiveRow $payment): ActiveRow
    {
        $this->paymentsRepository->update($payment, [
            'status' => PaymentStatusEnum::Paid->value,
            'paid_at' => new DateTime(),
        ]);
        return $this->paymentsRepository->find($payment->id);
    }
}
