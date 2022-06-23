<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\PaymentItem\DonationPaymentItem;
use Crm\PaymentsModule\PaymentItem\PaymentItemContainer;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;

class PaymentsRepositoryTest extends PaymentsTestCase
{
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

        /** @var SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository */
        $subscriptionTypeItemsRepository = $this->getRepository(SubscriptionTypeItemsRepository::class);
        $subscriptionTypeItemsRepository->update($subscriptionTypeItem, ['vat' => 10]);

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
}
