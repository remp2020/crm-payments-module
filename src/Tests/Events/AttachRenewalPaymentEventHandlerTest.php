<?php declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Events;

use Crm\ApplicationModule\Models\Event\LazyEventEmitter;
use Crm\PaymentsModule\Events\AttachRenewalPaymentEvent;
use Crm\PaymentsModule\Events\AttachRenewalPaymentEventHandler;
use Crm\PaymentsModule\Models\Gateways\Comfortpay;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\Payment\RenewalPayment;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Crm\UsersModule\Events\RemovedAccessTokenEvent;
use PHPUnit\Framework\Attributes\DataProvider;

class AttachRenewalPaymentEventHandlerTest extends PaymentsTestCase
{
    private LazyEventEmitter $lazyEventEmitter;

    private SubscriptionTypesRepository $subscriptionTypesRepository;

    private array $subscriptionTypes;

    public function setUp(): void
    {
        parent::setUp();

        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);

        $this->lazyEventEmitter = $this->inject(LazyEventEmitter::class);
        $this->lazyEventEmitter->addListener(
            AttachRenewalPaymentEvent::class,
            $this->inject(AttachRenewalPaymentEventHandler::class)
        );

        $this->subscriptionType('web_1', 10.0, ['web'], true);
        $this->subscriptionType('web_2', 20.0, ['web'], false);
        $this->subscriptionType('club_1', 50.0, ['web', 'print'], false);
        $this->subscriptionType('club_2', 40.0, ['web', 'mobile'], true);
        $this->subscriptionType('club_3', 60.0, ['web', 'mobile'], false);
    }

    public function tearDown(): void
    {
        $this->lazyEventEmitter->removeAllListeners(RemovedAccessTokenEvent::class);
        $this->subscriptionTypes = [];
        parent::tearDown();
    }

    public static function renewalDataProvider()
    {
        return [
            'SubscriptionWithoutPayment_ShouldUseDefaultSubscriptionType' => [
                'baseSubscription' => [
                    'subscriptionType' => 'web_1',
                ],
                'renewalPayment' => [
                    'subscriptionType' => 'web_1',
                    'amount' => 10.0,
                ],
            ],
            'SubscriptionWithNextSubscriptionType_ShouldUseDefaultSubscriptionTypeAnyway' => [
                'baseSubscription' => [
                    'subscriptionType' => 'web_1',
                    'nextSubscriptionType' => 'web_2',
                ],
                'renewalPayment' => [
                    'subscriptionType' => 'web_1', // web_1 (web) has matchable default subscription type, we ignore nextSubscriptionType
                    'amount' => 10.0,
                ],
            ],
            'SubscriptionWithNextSubscriptionType_ShouldUseNextSubscriptionType_NoDefaultMatched' => [
                'baseSubscription' => [
                    'subscriptionType' => 'club_1',
                    'nextSubscriptionType' => 'club_2',
                ],
                'renewalPayment' => [
                    'subscriptionType' => 'club_2', // club_1 (web+print) doesn't have any matchable default subscription type, we can honor nextSubscriptionType
                    'amount' => 40.0,
                ],
            ],
            'SubscriptionWithRecurrentPayment_ShouldUseRecurrentPaymentsResolvedContentAccessAndDefaultSubscriptionType' => [
                'baseSubscription' => [
                    'subscriptionType' => 'web_1',
                    'nextRecurrentSubscriptionType' => 'club_3', // if recurrent payment is involved, we resolve its next content access (web+club) and find its default (club_2)
                ],
                'renewalPayment' => [
                    'subscriptionType' => 'club_2',
                    'amount' => 40.0,
                ],
            ],
        ];
    }

    #[DataProvider('renewalDataProvider')]
    public function testAttachRenewalPayment($baseSubscription, $renewalPayment): void
    {
        $user = $this->getUser();

        if (isset($baseSubscription['nextSubscriptionType'])) {
            $this->subscriptionTypesRepository->update(
                $this->subscriptionTypes[$baseSubscription['subscriptionType']],
                [
                    'next_subscription_type_id' => $this->subscriptionTypes[$baseSubscription['nextSubscriptionType']]->id,
                ]
            );
        }

        /** @var SubscriptionsRepository $subscriptionsRepository */
        $subscriptionsRepository = $this->getRepository(SubscriptionsRepository::class);
        $subscription = $subscriptionsRepository->add(
            subscriptionType: $this->subscriptionTypes[$baseSubscription['subscriptionType']],
            isRecurrent: isset($baseSubscription['nextRecurrentSubscriptionType']) ?? false,
            isPaid: true,
            user: $user,
        );

        if (isset($baseSubscription['nextRecurrentSubscriptionType'])) {
            /** @var PaymentGatewaysRepository $paymentGatewaysRepository */
            $paymentGatewaysRepository = $this->getRepository(PaymentGatewaysRepository::class);
            $paymentGateway = $paymentGatewaysRepository->findBy('code', Comfortpay::GATEWAY_CODE);

            $paymentRow = $this->paymentsRepository->add(
                $this->subscriptionTypes[$baseSubscription['subscriptionType']],
                $paymentGateway,
                $user,
                new PaymentItemContainer(),
                null,
                1
            );

            $this->paymentsRepository->addSubscriptionToPayment($subscription, $paymentRow);

            $paymentRow = $this->paymentsRepository->updateStatus($paymentRow, PaymentStatusEnum::Paid->value);
            $recurrentPaymentRow = $this->recurrentPaymentsRepository->createFromPayment($paymentRow, 'recurrentToken');

            $this->recurrentPaymentsRepository->update($recurrentPaymentRow, [
                'next_subscription_type_id' => $this->subscriptionTypes[$baseSubscription['nextRecurrentSubscriptionType']]->id,
            ]);
        }

        $this->lazyEventEmitter->emit(new AttachRenewalPaymentEvent(
            subscriptionId: $subscription->id,
            userId: $user->id,
        ));

        /** @var RenewalPayment $renewalPayment */
        $renewalPaymentResolver = $this->inject(RenewalPayment::class);
        $payment = $renewalPaymentResolver->getRenewalPayment($subscription);

        $this->assertEquals($renewalPayment['subscriptionType'], $payment->subscription_type->code);
        $this->assertEquals($renewalPayment['amount'], $payment->amount);
    }

    protected function subscriptionType(string $code, float $price, array $contentAccess, bool $isDefault)
    {
        if (!isset($this->subscriptionTypes[$code])) {
            $subscriptionTypeBuilder = $this->container->getByType(SubscriptionTypeBuilder::class);
            $this->subscriptionTypes[$code] = $subscriptionTypeBuilder->createNew()
                ->setName($code)
                ->setUserLabel($code)
                ->setPrice($price)
                ->setCode($code)
                ->setLength(31)
                ->setActive(true)
                ->setDefault($isDefault)
                ->setContentAccessOption(...$contentAccess)
                ->save();
        }
        return $this->subscriptionTypes[$code];
    }
}
