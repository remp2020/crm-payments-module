<?php

namespace Crm\PaymentsModule\Tests;

use Crm\ApiModule\Tests\ApiTestTrait;
use Crm\PaymentsModule\Api\ListRecurrentPaymentsApiHandler;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Tests\TestUserTokenAuthorization;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Response\JsonApiResponse;

class ListRecurrentPaymentsApiHandlerTest extends PaymentsTestCase
{
    use ApiTestTrait;

    private ListRecurrentPaymentsApiHandler $handler;

    public function setUp(): void
    {
        parent::setUp();

        $this->recurrentPaymentsRepository = $this->inject(RecurrentPaymentsRepository::class);
        $this->handler = $this->inject(ListRecurrentPaymentsApiHandler::class);
    }

    public function tearDown(): void
    {
        unset($_GET['states']);
        unset($_GET['chargeable_from']);
        parent::tearDown();
    }

    public function testListAllRecurrentPayments()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByUser($rp1, $rp1->user_id);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertCount(2, $payload);
        $this->assertEquals(DateTime::from('2015-06-01')->format(DATE_RFC3339), $payload[0]['charge_at']);
        $this->assertEquals('user_stop', $payload[0]['state']);
        $this->assertEquals(DateTime::from('2016-06-01')->format(DATE_RFC3339), $payload[1]['charge_at']);
        $this->assertEquals('active', $payload[1]['state']);
    }

    public function testValidStatesSingleValue()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByUser($rp1, $rp1->user_id);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $_GET['states'] = ['active'];

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertCount(1, $payload);
        $this->assertEquals(DateTime::from('2016-06-01')->format(DATE_RFC3339), $payload[0]['charge_at']);
        $this->assertEquals('active', $payload[0]['state']);
    }

    public function testValidStatesMultiValue()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByAdmin($rp1);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $_GET['states'] = ['active', 'user_stop', 'admin_stop'];

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertCount(2, $payload);
        $this->assertEquals(DateTime::from('2015-06-01')->format(DATE_RFC3339), $payload[0]['charge_at']);
        $this->assertEquals('admin_stop', $payload[0]['state']);
        $this->assertEquals(DateTime::from('2016-06-01')->format(DATE_RFC3339), $payload[1]['charge_at']);
        $this->assertEquals('active', $payload[1]['state']);
    }

    public function testNoResult()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByAdmin($rp1);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $_GET['states'] = ['user_stop'];

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertCount(0, $payload);
    }

    public function testValidChargeableFrom()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByUser($rp1, $rp1->user_id);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $_GET['chargeable_from'] = '2016-01-01T01:23:45Z';

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertCount(1, $payload);
        $this->assertEquals(DateTime::from('2016-06-01')->format(DATE_RFC3339), $payload[0]['charge_at']);
        $this->assertEquals('active', $payload[0]['state']);
    }

    public function testInvalidChargeableFrom()
    {
        $payment = $this->createPayment('0000000001');
        $rp1 = $this->createRecurrentPayment('card', $payment, DateTime::from('2015-06-01'));
        $this->recurrentPaymentsRepository->stoppedByUser($rp1, $rp1->user_id);
        $rp2 = $this->createRecurrentPayment('card', $payment, DateTime::from('2016-06-01'));

        $_GET['chargeable_from'] = '2016-01-01 01:23:45++00';

        $this->handler->setAuthorization($this->getTestAuthorization($payment));
        $response = $this->runJsonApi($this->handler);
        $this->assertEquals(JsonApiResponse::class, get_class($response));

        $payload = $response->getPayload();
        $this->assertEquals('error', $payload['status']);
        $this->assertEquals('invalid_date', $payload['code']);
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

    protected function createPayment($variableSymbol)
    {
        $paymentItemContainer = (new PaymentItemContainer())->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($this->getSubscriptionType()));
        $payment = $this->paymentsRepository->add($this->getSubscriptionType(), $this->getPaymentGateway(), $this->getUser(), $paymentItemContainer);
        $this->paymentsRepository->update($payment, ['variable_symbol' => $variableSymbol]);
        return $payment;
    }

    private function getTestAuthorization($payment)
    {
        $token = $this->accessTokensRepository->add($payment->user, 3);
        return new TestUserTokenAuthorization($token);
    }
}
