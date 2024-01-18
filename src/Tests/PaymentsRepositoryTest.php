<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;

class PaymentsRepositoryTest extends PaymentsTestCase
{
    private SubscriptionTypesRepository $subscriptionTypesRepository;
    private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository;

    public function setUp(): void
    {
        parent::setUp();
        $this->subscriptionTypesRepository = $this->getRepository(SubscriptionTypesRepository::class);
        $this->subscriptionTypeItemsRepository = $this->getRepository(SubscriptionTypeItemsRepository::class);
    }

    public function testLoadVariableSymbolWithoutLeedingZeros()
    {
        $payment1 = $this->createPayment('0006789465');
        $payment2 = $this->createPayment('0106789465');
        $payment3 = $this->createPayment('4106789465');

        $p = $this->paymentsRepository->findByVs('6789465');

        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('06789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('006789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('0006789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('106789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment2->id);

        $p = $this->paymentsRepository->findByVs('4106789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment3->id);
    }

    public function testCopyPaymentWithSubscriptionTypeItem()
    {
        $payment = $this->createPayment('4106789466');

        $newPayment = $this->paymentsRepository->copyPayment($payment);

        $paymentItem = $payment->related('payment_items')->fetch();
        $newPaymentItem = $newPayment->related('payment_items')->fetch();

        $paymentItemArray = $paymentItem->toArray();
        $newPaymentItemArray = $newPaymentItem->toArray();

        unset(
            $paymentItemArray['id'],
            $newPaymentItemArray['id'],
            $paymentItemArray['payment_id'],
            $newPaymentItemArray['payment_id'],
            $paymentItemArray['created_at'],
            $newPaymentItemArray['created_at'],
            $paymentItemArray['updated_at'],
            $newPaymentItemArray['updated_at']
        );

        $this->assertEquals($payment->amount, $newPayment->amount);
        $this->assertEquals($paymentItemArray, $newPaymentItemArray);

        $paymentItemMeta = $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value');
        $newPaymentItemMeta = $newPaymentItem->related('payment_item_meta')->fetchPairs('key', 'value');

        $this->assertEquals($paymentItemMeta, $newPaymentItemMeta);
    }

    public function testCopyPaymentWithMultiplePaymentItemTypes()
    {
        $subscriptionType = $this->getSubscriptionType();

        $paymentItemContainer = new PaymentItemContainer();
        $paymentItemContainer->addItem(new DonationPaymentItem('donation', 4.5, 0));
        $paymentItemContainer->addItem(new SubscriptionTypePaymentItem(
            $subscriptionType->id,
            'subscription',
            7.6,
            10,
            1,
            ['subscription_type_item_key' => 'subscription_type_item_value']
        ));

        $payment = $this->paymentsRepository->add(
            $subscriptionType,
            $this->getPaymentGateway(),
            $this->getUser(),
            $paymentItemContainer
        );

        $newPayment = $this->paymentsRepository->copyPayment($payment);

        $paymentItems = $payment->related('payment_items');
        $newPaymentItems = $newPayment->related('payment_items');

        $this->assertCount(2, $newPaymentItems);

        $paymentItemDonation = (clone $paymentItems)->where(['type' => DonationPaymentItem::TYPE])->fetch();
        $newPaymentItemDonation = (clone $newPaymentItems)->where(['type' => DonationPaymentItem::TYPE])->fetch();

        $paymentItemDonationArray = $paymentItemDonation->toArray();
        $newPaymentItemDonationArray = $newPaymentItemDonation->toArray();

        unset(
            $paymentItemDonationArray['id'],
            $newPaymentItemDonationArray['id'],
            $paymentItemDonationArray['payment_id'],
            $newPaymentItemDonationArray['payment_id'],
            $paymentItemDonationArray['created_at'],
            $newPaymentItemDonationArray['created_at'],
            $paymentItemDonationArray['updated_at'],
            $newPaymentItemDonationArray['updated_at']
        );

        $this->assertEquals($paymentItemDonationArray, $newPaymentItemDonationArray);

        $paymentItemDonationMeta = $paymentItemDonation->related('payment_item_meta')->fetchPairs('key', 'value');
        $newPaymentItemDonationMeta = $newPaymentItemDonation->related('payment_item_meta')->fetchPairs('key', 'value');

        $this->assertEquals($paymentItemDonationMeta, $newPaymentItemDonationMeta);

        $paymentItemSubscriptionType = $paymentItems->where(['type' => SubscriptionTypePaymentItem::TYPE])->fetch();
        $newPaymentItemSubscriptionType = $newPaymentItems->where(['type' => SubscriptionTypePaymentItem::TYPE])->fetch();

        $paymentItemSubscriptionTypeArray = $paymentItemSubscriptionType->toArray();
        $newPaymentItemSubscriptionTypeArray = $newPaymentItemSubscriptionType->toArray();

        unset(
            $paymentItemSubscriptionTypeArray['id'],
            $newPaymentItemSubscriptionTypeArray['id'],
            $paymentItemSubscriptionTypeArray['payment_id'],
            $newPaymentItemSubscriptionTypeArray['payment_id'],
            $paymentItemSubscriptionTypeArray['created_at'],
            $newPaymentItemSubscriptionTypeArray['created_at'],
            $paymentItemSubscriptionTypeArray['updated_at'],
            $newPaymentItemSubscriptionTypeArray['updated_at']
        );

        $this->assertEquals($paymentItemSubscriptionTypeArray, $newPaymentItemSubscriptionTypeArray);

        $paymentItemSubscriptionTypeMeta = $paymentItemSubscriptionType->related('payment_item_meta')->fetchPairs('key', 'value');
        $newPaymentItemSubscriptionTypeMeta = $newPaymentItemSubscriptionType->related('payment_item_meta')->fetchPairs('key', 'value');

        $this->assertEquals($paymentItemSubscriptionTypeMeta, $newPaymentItemSubscriptionTypeMeta);
    }

    public function testCopyPaymentWithSubscriptionTypeAndChangedVat()
    {
        $payment = $this->createPayment('4106789467');
        $subscriptionType = $this->getSubscriptionType();
        $subscriptionTypeItem = $subscriptionType->related('subscription_type_item')->fetch();

        $this->subscriptionTypeItemsRepository->update($subscriptionTypeItem, ['vat' => 10]);

        $newPayment = $this->paymentsRepository->copyPayment($payment);

        $paymentItem = $payment->related('payment_items')->fetch();
        $newPaymentItem = $newPayment->related('payment_items')->fetch();

        $paymentItemMeta = $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value');
        $newPaymentItemMeta = $newPaymentItem->related('payment_item_meta')->fetchPairs('key', 'value');

        $this->assertEquals($paymentItemMeta, $newPaymentItemMeta);

        $paymentItemArray = $paymentItem->toArray();
        $newPaymentItemArray = $newPaymentItem->toArray();

        $this->assertNotEquals($paymentItemArray['vat'], $newPaymentItemArray['vat']);
        $this->assertNotEquals($paymentItemArray['amount_without_vat'], $newPaymentItemArray['amount_without_vat']);
        $this->assertEquals(10, $newPaymentItemArray['vat']);
        $this->assertEquals(PaymentItemHelper::getPriceWithoutVAT($newPaymentItemArray['amount'], 10), $newPaymentItemArray['amount_without_vat']);

        unset(
            $paymentItemArray['id'],
            $newPaymentItemArray['id'],
            $paymentItemArray['payment_id'],
            $newPaymentItemArray['payment_id'],
            $paymentItemArray['created_at'],
            $newPaymentItemArray['created_at'],
            $paymentItemArray['updated_at'],
            $newPaymentItemArray['updated_at'],
            $paymentItemArray['vat'],
            $newPaymentItemArray['vat'],
            $paymentItemArray['amount_without_vat'],
            $newPaymentItemArray['amount_without_vat'],
        );

        $this->assertEquals($paymentItemArray, $newPaymentItemArray);
    }

    public function testCopyPaymentWithChangedSubscriptionTypeItems(): void
    {
        $subscriptionType = $this->getSubscriptionType();
        $firstPayment = $this->createPayment('0001');

        $secondPayment = $this->paymentsRepository->copyPayment($firstPayment);
        $secondPayment = $this->paymentsRepository->find($secondPayment->id);

        $this->assertEquals(
            $firstPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
            $secondPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
        );

        // Payment with changed vat
        // soft delete related subscription_type_item
        $secondPaymentItem = $secondPayment->related('payment_items')->fetch();
        $secondPaymentSubscriptionTypeItem = $secondPaymentItem->subscription_type_item;

        $this->subscriptionTypeItemsRepository->softDelete($secondPaymentSubscriptionTypeItem);

        // and replace them with another one with lower vat so final price_without_vat won't be equal
        $this->subscriptionTypeItemsRepository->add(
            $subscriptionType,
            'third item',
            $secondPaymentSubscriptionTypeItem->amount,
            $secondPaymentSubscriptionTypeItem->vat - 10
        );

        // total price without amount is not equal so payment items will be created with actual items of subscription type
        $thirdPayment = $this->paymentsRepository->copyPayment($secondPayment);

        // so related payment items should not be equal
        $this->assertNotEquals(
            $secondPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
            $thirdPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
        );

        // Payment with changed VAT and same total price
        $thirdPaymentItem = $thirdPayment->related('payment_items')->fetch();
        $thirdPaymentSubscriptionTypeItem = $thirdPaymentItem->subscription_type_item;

        // soft delete related subscription_type_item
        $this->subscriptionTypeItemsRepository->softDelete($thirdPaymentSubscriptionTypeItem);

        // and replace them with another one with different price so final price_without_vat won't be equal
        $this->subscriptionTypeItemsRepository->add(
            $subscriptionType,
            'third item',
            $thirdPaymentSubscriptionTypeItem->amount + 1,
            $thirdPaymentSubscriptionTypeItem->vat
        );

        $this->subscriptionTypesRepository->update($subscriptionType, [
            'price' => $firstPayment->amount + 1,
        ]);

        // total price of payment is not equal with total price of subscription type
        $fourthPayment = $this->paymentsRepository->copyPayment($thirdPayment);

        // so related payment items should be equal with previous payment
        $this->assertEquals(
            $thirdPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
            $fourthPayment->related('payment_items')->fetchPairs('subscription_type_id', 'subscription_type_item_id'),
        );
    }

    public function testCopyPaymentWithHardChangedSubscriptionTypeItems()
    {
        $firstPayment = $this->createPayment('0002');

        $firstPaymentItem = $firstPayment->related('payment_items')->fetch();
        $firstPaymentSubscriptionTypeItem = $firstPaymentItem->subscription_type_item;
        $firstPaymentSubscriptionTypeAmount = $firstPaymentItem->subscription_type->price;

        // direct changed the amount of subscription type item
        $this->subscriptionTypeItemsRepository->update($firstPaymentSubscriptionTypeItem, [
            'amount' => $firstPaymentSubscriptionTypeItem->amount + 1,
        ]);

        $firstPayment = $this->paymentsRepository->find($firstPayment->id);

        // total amount of payment doesn't fit maybe legacy payment, copy items as are in previous payment
        $secondPayment = $this->paymentsRepository->copyPayment($firstPayment);

        $this->assertEquals(
            $firstPayment->related('payment_items')->fetchPairs('amount', 'subscription_type_item_id'),
            $secondPayment->related('payment_items')->fetchPairs('amount', 'subscription_type_item_id'),
        );
        $this->assertEquals($firstPayment->amount, $secondPayment->amount);
        $this->assertEquals($firstPaymentSubscriptionTypeAmount, $secondPayment->amount);
    }

    public function testCopyPaymentWithProperlyChangedSubscriptionTypeButDividedBetweenMoreItems(): void
    {
        $firstPayment = $this->createPayment('0003');

        $subscriptionType = $firstPayment->subscription_type;
        $firstSubscriptionTypeItem = $subscriptionType->related('subscription_type_item')->fetch();
        $firstSubscriptionTypeItemAmount = $firstSubscriptionTypeItem->amount;

        // divide one of subscription type items between two
        $this->subscriptionTypeItemsRepository->update($firstSubscriptionTypeItem, [
            'name' => 'divided item 1',
            'amount' => round($firstSubscriptionTypeItemAmount / 2, 2)
        ]);
        $this->subscriptionTypeItemsRepository->add($subscriptionType, 'divided item 2', round($firstSubscriptionTypeItemAmount / 2, 2), $firstSubscriptionTypeItem->vat);

        $secondPayment = $this->paymentsRepository->copyPayment($firstPayment);

        $this->assertNotEquals(
            $firstPayment->related('payment_items')->fetchPairs('amount', 'subscription_type_item_id'),
            $secondPayment->related('payment_items')->fetchPairs('amount', 'subscription_type_item_id'),
        );

        $this->assertEquals($firstPayment->amount, $secondPayment->amount);
    }
}
