<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionTypeItemsRepository;
use DateTime;
use Tomaj\BankMailsParser\MailContent;

class MailProcessorTest extends PaymentsTestCase
{
    private MailProcessor $mailProcessor;

    private ParsedMailLogsRepository $parsedMailLogsRepository;

    private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository;

    public function setup(): void
    {
        parent::setup();

        $this->mailProcessor = $this->container->getByType('Crm\PaymentsModule\MailConfirmation\MailProcessor');
        $this->parsedMailLogsRepository = $this->getRepository(ParsedMailLogsRepository::class);
        $this->subscriptionTypeItemsRepository = $this->getRepository(SubscriptionTypeItemsRepository::class);
    }

    public function testMailWithoutVariableSymbol()
    {
        $mailContent = new MailContent();
        $mailContent->setAmount(10.2);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();

        $this->assertEquals(ParsedMailLogsRepository::STATE_WITHOUT_VS, $log->state);
    }

    public function testMailWithWrongAmount()
    {
        $payment = $this->createPayment('7492857611');
        $this->paymentsRepository->update($payment, array('amount' => 10.3));

        $mailContent = new MailContent();
        $mailContent->setAmount(10.2);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs('7492857611');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT, $log->state);
    }

    public function testAlreadyPaidPaymentDoNothing()
    {
        $payment = $this->createPayment('7492857631');
        $this->paymentsRepository->update($payment, array('amount' => 10.2, 'status' => PaymentsRepository::STATUS_PAID));

        $mailContent = new MailContent();
        $mailContent->setAmount(10.2);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs('7492857631');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_ALREADY_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals($newPayment->status, $newPayment->status);
    }

    public function testNotFoundVariableSymbol()
    {
        $payment = $this->createPayment('7492857611');
        $this->paymentsRepository->update($payment, array('amount' => 10.3));

        $mailContent = new MailContent();
        $mailContent->setAmount(10.2);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs('7492857612');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND, $log->state);
    }

    public function testSuccessfullyPaymentChange()
    {
        $payment = $this->createPayment('7492857612');
        $this->paymentsRepository->update($payment, array('amount' => 10.2));
        $this->assertEquals(PaymentsRepository::STATUS_FORM, $payment->status);

        $mailContent = new MailContent();
        $mailContent->setAmount(10.2);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs('7492857612');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $newPayment->status);
    }

    public function testRepeatedPaymentFromBank()
    {
        $payment = $this->createPayment('7492851612');
        $this->paymentsRepository->update($payment, [
            'amount' => 10.4,
            'status' => PaymentsRepository::STATUS_PAID,
            'created_at' => new DateTime('23 days ago'),
        ]);

        $subscriptionTypeItem = $payment->subscription_type->related('subscription_type_item')->fetch();
        $this->subscriptionTypeItemsRepository->update($subscriptionTypeItem, ['vat' => 20]);

        $mailContent = new MailContent();
        $mailContent->setAmount(10.4);
        $mailContent->setTransactionDate(strtotime('now'));
        $mailContent->setVs('7492851612');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());

        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT, $log->state);

        $newPayment = $this->paymentsRepository->findLastByVS('7492851612');
        $this->assertNotEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $newPayment->status);

        foreach ($payment->related('payment_items') as $paymentItem) {
            $newPaymentItem = $newPayment->related('payment_items')->where('name', $paymentItem->name)->fetch();

            $paymentItemArray = $paymentItem->toArray();
            $newPaymentItemArray = $newPaymentItem->toArray();

            $this->assertEquals(20, $newPaymentItemArray['vat']);
            $this->assertEquals(PaymentItemHelper::getPriceWithoutVAT($newPaymentItemArray['amount'], 20), $newPaymentItemArray['amount_without_vat']);

            unset(
                $paymentItemArray['id'],
                $paymentItemArray['payment_id'],
                $paymentItemArray['created_at'],
                $paymentItemArray['updated_at'],
                $paymentItemArray['vat'],
                $paymentItemArray['amount_without_vat'],
                $newPaymentItemArray['id'],
                $newPaymentItemArray['payment_id'],
                $newPaymentItemArray['created_at'],
                $newPaymentItemArray['updated_at'],
                $newPaymentItemArray['vat'],
                $newPaymentItemArray['amount_without_vat'],
            );

            $this->assertEqualsCanonicalizing($paymentItemArray, $newPaymentItemArray);

            $paymentItemMeta = $paymentItem->related('payment_item_meta')->fetchPairs('key', 'value');
            $newPaymentItemMeta = $newPaymentItem->related('payment_item_meta')->fetchPairs('key', 'value');

            $this->assertEqualsCanonicalizing($paymentItemMeta, $newPaymentItemMeta);
        }
    }

    public function testDuplicatedPaymentByUser()
    {
        // mock first payment confirmation
        $payment = $this->createPayment('7492851612');
        $this->paymentsRepository->update($payment, array(
            'amount' => 10.4,
            'status' => PaymentsRepository::STATUS_PAID,
            'created_at' => new DateTime('2 days ago'),
        ));
        $this->parsedMailLogsRepository->insert([
            'created_at' => new DateTime('2 days ago'),
            'delivered_at' => new DateTime('2 days ago'),
            'variable_symbol' => $payment->variable_symbol,
            'amount' => $payment->amount,
            'state' => ParsedMailLogsRepository::STATE_CHANGED_TO_PAID,
        ]);

        // user for some reason paid again 2 days later
        $mailContent = new MailContent();
        $mailContent->setAmount($payment->amount);
        $mailContent->setTransactionDate(time());
        $mailContent->setVs($payment->variable_symbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_DUPLICATED_PAYMENT, $log->state);
    }

    public function testAlreadyRefundedPayment()
    {
        $payment = $this->createPayment('7492851612');
        $this->paymentsRepository->update($payment, array(
            'amount' => 10.4,
            'status' => PaymentsRepository::STATUS_REFUND,
            'created_at' => new DateTime('23 days ago')
        ));

        $mailContent = new MailContent();
        $mailContent->setAmount(10.4);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs('7492851612');

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_ALREADY_REFUNDED, $log->state);
    }
}
