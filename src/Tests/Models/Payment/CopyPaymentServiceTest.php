<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Models\Payment;

use Crm\PaymentsModule\Models\Payment\CopyPaymentService;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Tests\PaymentsTestCase;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Nette\Database\Table\ActiveRow;

class CopyPaymentServiceTest extends PaymentsTestCase
{
    private CopyPaymentService $copyPaymentService;
    private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->copyPaymentService = $this->inject(CopyPaymentService::class);
        $this->subscriptionTypeItemsRepository = $this->getRepository(SubscriptionTypeItemsRepository::class);
    }

    public function testPaymentWithoutSubscriptionTypeReturnsEmptyContainer(): void
    {
        $container = (new PaymentItemContainer())
            ->addItem(new DonationPaymentItem('donation', 5.0, 0));

        $payment = $this->paymentsRepository->add(
            subscriptionType: null,
            paymentGateway: $this->getPaymentGateway(),
            user: $this->getUser(),
            paymentItemContainer: $container,
        );

        $result = $this->copyPaymentService->copySubscriptionTypeItems($payment);

        $this->assertSame([], $result->items());
        $this->assertSame(0.0, $result->totalPrice());
    }

    public function testUnchangedSubscriptionTypeCopiesPaymentItems(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        // same prices, VAT rates and item count as the subscription type, only names differ
        // so we can tell whether items were copied or rebuilt
        $payment = $this->createPaymentWithItems($subscriptionType, [
            ['name' => 'copied first item', 'amount' => 10.0, 'vat' => 20.0],
            ['name' => 'copied second item', 'amount' => 2.2, 'vat' => 20.0],
        ]);

        $result = $this->copyPaymentService->copySubscriptionTypeItems($payment);

        $this->assertSame(
            ['copied first item', 'copied second item'],
            $this->itemNames($result),
        );
        $this->assertEqualsWithDelta(12.2, $result->totalPrice(), 0.001);
    }

    public function testOnlyVatChangedRebuildsItemsFromSubscriptionType(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $payment = $this->createPaymentWithItems($subscriptionType, [
            ['name' => 'copied first item', 'amount' => 10.0, 'vat' => 20.0],
            ['name' => 'copied second item', 'amount' => 2.2, 'vat' => 20.0],
        ]);

        // VAT rate change absorbed in the consumer price - total price with VAT stays the same,
        // total price without VAT changes
        foreach ($this->subscriptionTypeItemsRepository->subscriptionTypeItems($subscriptionType) as $item) {
            $this->subscriptionTypeItemsRepository->update($item, ['vat' => 10.0], force: true);
        }

        $result = $this->copyPaymentService->copySubscriptionTypeItems($this->refreshPayment($payment));

        $this->assertSame(['first item', 'second item'], $this->itemNames($result));
        $this->assertSame([10.0, 10.0], array_map(fn ($item) => $item->vat(), $result->items()));
        $this->assertEqualsWithDelta(12.2, $result->totalPrice(), 0.001);
    }

    public function testOnlyItemsCountChangedRebuildsItemsFromSubscriptionType(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        // one payment item instead of the two items of the subscription type, same total price and VAT
        $payment = $this->createPaymentWithItems($subscriptionType, [
            ['name' => 'copied single item', 'amount' => 12.2, 'vat' => 20.0],
        ]);

        $result = $this->copyPaymentService->copySubscriptionTypeItems($payment);

        $this->assertSame(['first item', 'second item'], $this->itemNames($result));
        $this->assertEqualsWithDelta(12.2, $result->totalPrice(), 0.001);
    }

    public function testPriceChangedPreservesCopiedItemsByDefault(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        // payment was cheaper than the current subscription type price (repricing, discount, ...)
        $payment = $this->createPaymentWithItems($subscriptionType, [
            ['name' => 'copied first item', 'amount' => 8.0, 'vat' => 20.0],
            ['name' => 'copied second item', 'amount' => 2.0, 'vat' => 20.0],
        ]);

        $result = $this->copyPaymentService->copySubscriptionTypeItems($payment);

        $this->assertSame(
            ['copied first item', 'copied second item'],
            $this->itemNames($result),
        );
        $this->assertEqualsWithDelta(10.0, $result->totalPrice(), 0.001);
    }

    public function testPriceChangedRebuildsItemsWhenCopyIsNotPreserved(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $payment = $this->createPaymentWithItems($subscriptionType, [
            ['name' => 'copied first item', 'amount' => 8.0, 'vat' => 20.0],
            ['name' => 'copied second item', 'amount' => 2.0, 'vat' => 20.0],
        ]);

        $result = $this->copyPaymentService->copySubscriptionTypeItems(
            $payment,
            preserveCopiedItemsOnPriceChange: false,
        );

        $this->assertSame(['first item', 'second item'], $this->itemNames($result));
        $this->assertEqualsWithDelta(12.2, $result->totalPrice(), 0.001);
    }

    public function testOtherPaymentItemTypesAreNotCopied(): void
    {
        $subscriptionType = $this->createSubscriptionType();
        $container = (new PaymentItemContainer())
            ->addItems(SubscriptionTypePaymentItem::fromSubscriptionType($subscriptionType))
            ->addItem(new DonationPaymentItem('donation', 5.0, 0));

        $payment = $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->getPaymentGateway(),
            user: $this->getUser(),
            paymentItemContainer: $container,
        );

        $result = $this->copyPaymentService->copySubscriptionTypeItems($payment);

        $this->assertSame(['first item', 'second item'], $this->itemNames($result));
        $this->assertEqualsWithDelta(12.2, $result->totalPrice(), 0.001);
    }

    private function createSubscriptionType(): ActiveRow
    {
        /** @var SubscriptionTypeBuilder $subscriptionTypeBuilder */
        $subscriptionTypeBuilder = $this->inject(SubscriptionTypeBuilder::class);

        return $subscriptionTypeBuilder->createNew()
            ->setName('copy payment subscription type')
            ->setUserLabel('copy payment subscription type')
            ->setPrice(12.2)
            ->addSubscriptionTypeItem('first item', 10.0, 20.0)
            ->addSubscriptionTypeItem('second item', 2.2, 20.0)
            ->setCode('copy_payment_subscription_type')
            ->setLength(31)
            ->setActive(true)
            ->save();
    }

    private function createPaymentWithItems(ActiveRow $subscriptionType, array $items): ActiveRow
    {
        $container = new PaymentItemContainer();
        foreach ($items as $item) {
            $container->addItem(new SubscriptionTypePaymentItem(
                subscriptionTypeId: $subscriptionType->id,
                name: $item['name'],
                price: $item['amount'],
                vat: $item['vat'],
            ));
        }

        return $this->paymentsRepository->add(
            subscriptionType: $subscriptionType,
            paymentGateway: $this->getPaymentGateway(),
            user: $this->getUser(),
            paymentItemContainer: $container,
        );
    }

    private function refreshPayment(ActiveRow $payment): ActiveRow
    {
        return $this->paymentsRepository->find($payment->id);
    }

    private function itemNames(PaymentItemContainer $container): array
    {
        return array_map(fn ($item) => $item->name(), $container->items());
    }
}
