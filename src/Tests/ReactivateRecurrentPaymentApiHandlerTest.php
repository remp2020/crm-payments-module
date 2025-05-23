<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\PaymentsModule\Api\ReactivateRecurrentPaymentApiHandler;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Tests\TestUserTokenAuthorization;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ReactivateRecurrentPaymentApiHandlerTest extends PaymentsTestCase
{
    use ApiTestTrait;

    private ReactivateRecurrentPaymentApiHandler $handler;
    private UserManager $userManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);

        // Api handler we want to test
        $this->handler = $this->inject(ReactivateRecurrentPaymentApiHandler::class);
    }

    public function testSuccessfulReactivate()
    {
        // create payment & recurrent payment & stop it (by user)
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);
        $recurrentPayment = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPayment, $user);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(json_encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());
        $payload = $response->getPayload();
        // status should change to `active`; other fields are same
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $payload['state']);
        $this->assertEquals($recurrentPayment->id, $payload['id']);
        $this->assertEquals($recurrentPayment->parent_payment_id, $payload['parent_payment_id']);
        $this->assertEquals($recurrentPayment->charge_at, $payload['charge_at']);
        $this->assertEquals($recurrentPayment->payment_gateway->code, $payload['payment_gateway_code']);
        $this->assertEquals($recurrentPayment->subscription_type->code, $payload['subscription_type_code']);
        $this->assertEquals($recurrentPayment->retries, $payload['retries']);

        // validate state in DB (to be sure)
        $recurrentPaymentStopped = $this->recurrentPaymentsRepository->find($payload['id']);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPaymentStopped->state);
    }

    public function testMissingPayload()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);
        $recurrentPayment = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPayment, $user);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment->state);

        // call API
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPaymentReloaded->state);
    }

    public function testNotFoundRecurrentPayment()
    {
        $user = $this->createUser('test@example.com');
        $notFoundRecurrentPaymentID = 424242;
        // no recurrent payment created; check it
        $recurrentPayment = $this->recurrentPaymentsRepository->find($notFoundRecurrentPaymentID);
        $this->assertNull($recurrentPayment);

        // call API
        $this->handler->setRawPayload(json_encode(['id' => $notFoundRecurrentPaymentID]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_found', $payload['code']);
    }

    // only recurrent payment in state `user_stop` can be reactivated by user
    public function testRecurrentPaymentInNonUserStopState()
    {
        $user = $this->createUser('test@example.com');

        // create payment & recurrent payment
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);
        // change state of recurrent payment to already `charged` (skipping process with events; just update DB state)
        $this->recurrentPaymentsRepository->update($recurrentPayment, ['state' => RecurrentPaymentStateEnum::Charged->value]);
        $this->assertEquals(RecurrentPaymentStateEnum::Charged->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(json_encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S409_CONFLICT, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_active', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Charged->value, $recurrentPaymentReloaded->state);
    }

    public function testIncorrectUserUsed()
    {
        // create first user, payment & recurrent payment
        $user1 = $this->createUser('test1@example.com');
        $payment1 = $this->createPaymentWithUser('0000000001', $user1);
        $recurrentPayment1 = $this->createRecurrentPayment('card', $payment1, clone($payment1->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment1->state);
        $recurrentPayment1 = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPayment1, $user1);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment1->state);

        // create second user, payment & recurrent payment
        $user2 = $this->createUser('test2@example.com');
        $payment2 = $this->createPaymentWithUser('0000000001', $user2);
        $recurrentPayment2 = $this->createRecurrentPayment('card', $payment2, clone($payment2->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment2->state);
        $recurrentPayment2 = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPayment2, $user2);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment2->state);

        // call API with mismatched recurrent payment (1) and user (2)
        $this->handler->setRawPayload(json_encode(['id' => $recurrentPayment1->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user2));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_found', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged for both recurrent payments; stopping failed
        $recurrentPayment1Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment1->id);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment1Reloaded->state);
        $recurrentPayment2Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment2->id);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment2Reloaded->state);
    }

    public function testChargeInPast()
    {
        // create payment & recurrent payment & stop it (by user)
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        // setting charge_at in past
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('-1 day'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);
        $recurrentPayment = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPayment, $user);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(json_encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());
        $payload = $response->getPayload();
        // status should change to `active`; other fields are same
        $this->assertEquals('recurrent_payment_next_charge_in_past', $payload['code']);

        // validate state in DB (to be sure)
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPaymentReloaded->state);
    }

    private function createRecurrentPayment(string $cid, ActiveRow $payment, \DateTime $chargeAt)
    {
        $paymentMethod = $this->paymentMethodsRepository->findOrAdd(
            $payment->user_id,
            $payment->payment_gateway_id,
            $cid,
        );

        return $this->recurrentPaymentsRepository->add(
            $paymentMethod,
            $payment,
            $chargeAt,
            null,
            1,
        );
    }

    protected function createPaymentWithUser(string $variableSymbol, ActiveRow $user)
    {
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($this->getSubscriptionType()));
        $payment = $this->paymentsRepository->add($this->getSubscriptionType(), $this->getPaymentGateway(), $user, $paymentItemContainer);
        $this->paymentsRepository->update($payment, ['variable_symbol' => $variableSymbol]);
        return $payment;
    }

    private function createUser($email)
    {
        return $this->userManager->addNewUser(
            $email,
            false,
            'unknown',
            null,
            false,
            null,
            true, // create access token
        );
    }

    private function getTestAuthorization(ActiveRow $user)
    {
        $token = $this->accessTokensRepository->allUserTokens($user->id)->limit(1)->fetch();
        return new TestUserTokenAuthorization($token);
    }
}
