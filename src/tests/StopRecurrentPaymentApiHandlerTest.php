<?php

namespace Crm\UsersModule\Tests;

use Crm\ApiModule\Api\JsonResponse;
use Crm\PaymentsModule\Api\StopRecurrentPaymentApiHandler;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Auth\UserManager;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\IRow;
use Nette\Http\Response;
use Nette\Utils\Json;

class StopRecurrentPaymentApiHandlerTest extends PaymentsTestCase
{
    /** @var StopRecurrentPaymentApiHandler */
    private $handler;

    /** @var UserManager */
    private $userManager;

    public function setUp(): void
    {
        parent::setUp();

        $this->userManager = $this->inject(UserManager::class);
        $this->recurrentPaymentsRepository = $this->inject(RecurrentPaymentsRepository::class);

        // Api handler we want to test
        $this->handler = $this->inject(StopRecurrentPaymentApiHandler::class);
    }

    public function testSuccessfulStop()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
        $response = $this->handler->handle($this->getTestAuthorization($user));

        // validate API response
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S200_OK, $response->getHttpCode());
        $payload = $response->getPayload();
        // status should change to `user_stop`; other fields are same
        $this->assertEquals(RecurrentPaymentsRepository::STATE_USER_STOP, $payload['state']);
        $this->assertEquals($recurrentPayment->id, $payload['id']);
        $this->assertEquals($recurrentPayment->parent_payment_id, $payload['parent_payment_id']);
        $this->assertEquals($recurrentPayment->charge_at, $payload['charge_at']);
        $this->assertEquals($recurrentPayment->payment_gateway->code, $payload['payment_gateway_code']);
        $this->assertEquals($recurrentPayment->subscription_type->code, $payload['subscription_type_code']);
        $this->assertEquals($recurrentPayment->retries, $payload['retries']);

        // validate state in DB (to be sure)
        $recurrentPaymentStopped = $this->recurrentPaymentsRepository->find($payload['id']);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_USER_STOP, $recurrentPaymentStopped->state);
    }

    public function testMissingPayload()
    {
        // create payment & recurrent payment
        $user = $this->createUser('test@example.com');
        $payment = $this->createPaymentWithUser('0000000001', $user);
        $recurrentPayment = $this->createRecurrentPayment('card', $payment, clone($payment->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment->state);

        // call API
        $response = $this->handler->handle($this->getTestAuthorization($user));

        // validate API response
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S400_BAD_REQUEST, $response->getHttpCode());

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPaymentReloaded->state);
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
        $response = $this->handler->handle($this->getTestAuthorization($user));

        // validate API response
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());
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
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment->state);
        // change state of recurrent payment to already `charged` (skipping process with events; just update DB state)
        $this->recurrentPaymentsRepository->update($recurrentPayment, ['state' => RecurrentPaymentsRepository::STATE_CHARGED]);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_CHARGED, $recurrentPayment->state);

        // call API
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment->id]));
        $response = $this->handler->handle($this->getTestAuthorization($user));

        // validate API response
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S409_CONFLICT, $response->getHttpCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_active', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged; stopping failed
        $recurrentPaymentReloaded = $this->recurrentPaymentsRepository->find($recurrentPayment->id);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_CHARGED, $recurrentPaymentReloaded->state);
    }

    public function testIncorrectUserUsed()
    {
        // create first user, payment & recurrent payment
        $user1 = $this->createUser('test1@example.com');
        $payment1 = $this->createPaymentWithUser('0000000001', $user1);
        $recurrentPayment1 = $this->createRecurrentPayment('card', $payment1, clone($payment1->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment1->state);

        // create second user, payment & recurrent payment
        $user2 = $this->createUser('test2@example.com');
        $payment2 = $this->createPaymentWithUser('0000000001', $user2);
        $recurrentPayment2 = $this->createRecurrentPayment('card', $payment2, clone($payment2->created_at)->modify('+1 month'));
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment2->state);

        // call API with mismatched recurrent payment (1) and user (2)
        $this->handler->setRawPayload(Json::encode(['id' => $recurrentPayment1->id]));
        $response = $this->handler->handle($this->getTestAuthorization($user2));

        // validate API response
        $this->assertEquals(JsonResponse::class, get_class($response));
        $this->assertEquals(Response::S404_NOT_FOUND, $response->getHttpCode());
        $payload = $response->getPayload();
        $this->assertEquals('recurrent_payment_not_found', $payload['code']);

        // validate state in DB (to be sure) - should be unchanged for both recurrent payments; stopping failed
        $recurrentPayment1Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment1->id);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment1Reloaded->state);
        $recurrentPayment2Reloaded = $this->recurrentPaymentsRepository->find($recurrentPayment2->id);
        $this->assertEquals(RecurrentPaymentsRepository::STATE_ACTIVE, $recurrentPayment2Reloaded->state);
    }

    private function createRecurrentPayment(string $cid, ActiveRow $payment, \DateTime $chargeAt)
    {
        return $this->recurrentPaymentsRepository->add(
            $cid,
            $payment,
            $chargeAt,
            null,
            1
        );
    }

    protected function createPaymentWithUser(string $variableSymbol, IRow $user)
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
            true // create access token
        );
    }

    private function getTestAuthorization(IRow $user)
    {
        $token = $this->accessTokensRepository->allUserTokens($user->id)->limit(1)->fetch();
        return new TestUserTokenAuthorization($token);
    }
}
