<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\PaymentsModule\Api\StopRecurrentPaymentApiHandler;
use Crm\PaymentsModule\Models\Gateway;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\PaymentGatewayMetaRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Models\Auth\UserManager;
use Crm\UsersModule\Tests\TestUserTokenAuthorization;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Utils\Json;
use Tomaj\NetteApi\Response\JsonApiResponse;

class StopRecurrentPaymentApiHandlerTest extends PaymentsTestCase
{
    use ApiTestTrait;

    private StopRecurrentPaymentApiHandler $handler;
    private UserManager $userManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->recurrentPaymentsRepository = $this->inject(RecurrentPaymentsRepository::class);

        // Api handler we want to test
        $this->handler = $this->inject(StopRecurrentPaymentApiHandler::class);
    }

    public function testUnstoppableGateway()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->setGatewayAsUnstoppable($this->getPaymentGateway());

        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S403_FORBIDDEN, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('user_unstoppable_recurrent_payment_gateway', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPaymentReloaded->state);

        // Reset gateway to default "stoppable" status
        $this->resetGatewayUnstoppableStatus($this->getPaymentGateway());
    }

    public function testUserUnstoppableGateway()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->setGatewayAsUserUnstoppable($this->getPaymentGateway());

        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S403_FORBIDDEN, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('user_unstoppable_recurrent_payment_gateway', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPaymentReloaded->state);

        // Reset gateway to default "stoppable" status
        $this->resetGatewayUnstoppableStatus($this->getPaymentGateway());
    }

    public function testSuccessfulStop()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getCode());
        $payload = $response->getPayload();
        // status should change to `user_stop`; other fields are same
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $payload['state']);
        $this->assertEquals($recurrentPayment->id, $payload['id']);
        $this->assertEquals($recurrentPayment->parent_payment_id, $payload['parent_payment_id']);
        $this->assertEquals($recurrentPayment->charge_at, $payload['charge_at']);
        $this->assertEquals($recurrentPayment->payment_gateway->code, $payload['payment_gateway_code']);
        $this->assertEquals($recurrentPayment->subscription_type->code, $payload['subscription_type_code']);
        $this->assertEquals($recurrentPayment->retries, $payload['retries']);

        // validate state in DB (to be sure)
        $recurrentPaymentStopped = $this->recurrentPaymentsRepository->find($payload['id']);
        $this->assertEquals(RecurrentPaymentStateEnum::UserStop->value, $recurrentPaymentStopped->state);
    }

    public function testMissingPayload()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment->state);

        // call API
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getCode());

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPaymentReloaded->state);
    }

    public function testNotFoundRecurrentPayment()
    {
        $user = $this->createUser('test@example.com');
        $notFoundRecurrentPaymentID = 424242;
        // no recurrent payment created; check it
        $recurrentPayment = $this->recurrentPaymentsRepository->find($notFoundRecurrentPaymentID);
        $this->assertNull($recurrentPayment);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $notFoundRecurrentPaymentID]));
        $this->handler->setAuthorization($this->getTestAuthorization($user));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_found', $payload['code']);
    }

    // only active recurrent payment can be stopped by user
    public function testRecurrentPaymentInNonActiveState()
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
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
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

        // create second user, payment & recurrent payment
        $user2 = $this->createUser('test2@example.com');
        $payment2 = $this->createPaymentWithUser('0000000001', $user2);
        $recurrentPayment2 = $this->createRecurrentPayment('card', $payment2, clone($payment2->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment2->state);

        // call API with mismatched recurrent payment (1) and user (2)
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment1->id]));
        $this->handler->setAuthorization($this->getTestAuthorization($user2));
        $response = $this->runJsonApi($this->handler);

        // validate API response
        $this->assertEquals(JsonApiResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_found', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged for both recurrent payments; stopping failed
        $recurrentPayment1Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment1->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment1Reloaded->state);
        $recurrentPayment2Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment2->id);
        $this->assertEquals(RecurrentPaymentStateEnum::Active->value, $recurrentPayment2Reloaded->state);
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

    protected function setGatewayAsUnstoppable(ActiveRow $paymentGateway)
    {
        /** @var PaymentGatewayMetaRepository $paymentGatewayMetaRepository */
        $paymentGatewayMetaRepository = $this->getRepository(PaymentGatewayMetaRepository::class);
        $paymentGatewayMetaRepository->add($paymentGateway, Gateway::META_UNSTOPPABLE, '1');
    }

    protected function setGatewayAsUserUnstoppable(ActiveRow $paymentGateway)
    {
        /** @var PaymentGatewayMetaRepository $paymentGatewayMetaRepository */
        $paymentGatewayMetaRepository = $this->getRepository(PaymentGatewayMetaRepository::class);
        $paymentGatewayMetaRepository->add($paymentGateway, Gateway::META_USER_UNSTOPPABLE, '1');
    }

    protected function resetGatewayUnstoppableStatus(ActiveRow $paymentGateway)
    {
        /** @var PaymentGatewayMetaRepository $paymentGatewayMetaRepository */
        $paymentGatewayMetaRepository = $this->getRepository(PaymentGatewayMetaRepository::class);
        $paymentGatewayMetaRepository->findByPaymentGatewayAndKey($paymentGateway, Gateway::META_UNSTOPPABLE)->delete();
        $paymentGatewayMetaRepository->findByPaymentGatewayAndKey($paymentGateway, Gateway::META_USER_UNSTOPPABLE)->delete();
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
