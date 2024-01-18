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

        $this->mailProcessor = $this->container->getByType(\Crm\PaymentsModule\MailConfirmation\MailProcessor::class);
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
        $variableSymbol = '7492857611';
        $amount = 10.3;
        $amountInvalid = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amountInvalid);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT, $log->state);
    }

    public function testAlreadyPaidPaymentDoNothing()
    {
        $variableSymbol = '7492857631';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount, 'status' => PaymentsRepository::STATUS_PAID));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_ALREADY_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals($newPayment->status, $newPayment->status);
    }

    public function testNoPaymentForVariableSymbol()
    {
        $variableSymbol = '7492857611';
        $variableSymbolInvalid = '7492857612';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolInvalid);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND, $log->state);
    }

    public function testVariableSymbolInReceiverMessage()
    {
        $variableSymbol = '7492857611';
        $variableSymbolInvalid = '7492857612';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolInvalid); // incorrect
        $mailContent->setReceiverMessage('vs' . $variableSymbol); // correct (with VS prefix)

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $newPayment->status);
    }

    public function testVariableSymbolInReceiverMessageWithoutPrefix()
    {
        $variableSymbol = '7492857611';
        $variableSymbolInvalid = '7492857612';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolInvalid); // incorrect
        $mailContent->setReceiverMessage($variableSymbol); // correct (without VS prefix)

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $newPayment->status);
    }

    public function testIncorrectVariableSymbolInReceiverMessage()
    {
        $variableSymbol = '7492857611';
        $variableSymbolInvalid = '7492857612';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolInvalid); // incorrect
        $mailContent->setReceiverMessage('VS' . $variableSymbolInvalid); // incorrect

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND, $log->state);
    }

    public function testInvalidFormatOfVariableSymbolInReceiverMessage()
    {
        $variableSymbol = '7492857611';
        $variableSymbolInvalid = '749285'; // six characters only; eight are required when used without prefix
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolInvalid);
        $mailContent->setReceiverMessage($variableSymbolInvalid);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND, $log->state);
    }

    public function testSuccessfullyPaymentChange()
    {
        $variableSymbol = '7492857611';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array('amount' => $amount));
        $this->assertEquals(PaymentsRepository::STATUS_FORM, $payment->status);

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID, $log->state);

        $newPayment = $this->paymentsRepository->find($payment->id);
        $this->assertEquals($newPayment->id, $payment->id);
        $this->assertEquals($newPayment->variable_symbol, $payment->variable_symbol);
        $this->assertEquals(PaymentsRepository::STATUS_PAID, $newPayment->status);
    }

    // should test that VS set by library has precedence over VS found by MailProcessor in receiver message
    public function testTwoPaymentsTwoVariableSymbolsOneInVsOneInReceiverMessage()
    {
        $variableSymbolPayment1 = '7492857611';
        $variableSymbolPayment2 = '7492857612';
        $amount = 10.2;

        $payment1 = $this->createPayment($variableSymbolPayment1);
        $this->paymentsRepository->update($payment1, array('amount' => $amount));

        $payment2 = $this->createPayment($variableSymbolPayment2);
        $this->paymentsRepository->update($payment2, array('amount' => $amount));

        $paidPayments = $this->paymentsRepository->getTable()->where('status = ?', PaymentsRepository::STATUS_PAID)->count('*');
        $this->assertEquals(0, $paidPayments); // zero paid payments

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbolPayment1); // correct VS for first payment; should be used to confirm payment
        $mailContent->setReceiverMessage($variableSymbolPayment2); // correct VS for second payment; shouldn't be used

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID, $log->state);

        $paidPayments = $this->paymentsRepository->getTable()->where('status = ?', PaymentsRepository::STATUS_PAID)->count('*');
        $this->assertEquals(1, $paidPayments); // one one payment is paid

        $firstPayment = $this->paymentsRepository->find($payment1->id);
        $secondPayment = $this->paymentsRepository->find($payment2->id);

        $this->assertEquals(PaymentsRepository::STATUS_PAID, $firstPayment->status); // confirmed / paid by mail processor
        $this->assertEquals(PaymentsRepository::STATUS_FORM, $secondPayment->status); // not confirmed by mail processor
    }

    public function testRepeatedPaymentFromBank()
    {
        $variableSymbol = '7492857611';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, [
            'amount' => $amount,
            'status' => PaymentsRepository::STATUS_PAID,
            'created_at' => new DateTime('23 days ago'),
        ]);

        $subscriptionTypeItem = $payment->subscription_type->related('subscription_type_item')->fetch();
        $this->subscriptionTypeItemsRepository->update($subscriptionTypeItem, ['vat' => 20]);

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('now'));
        $mailContent->setVs($variableSymbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());

        $this->assertTrue($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT, $log->state);

        $newPayment = $this->paymentsRepository->findLastByVS($variableSymbol);
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
        $variableSymbol = '7492857611';
        $amount = 10.2;

        // mock first payment confirmation
        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array(
            'amount' => $amount,
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
        $variableSymbol = '7492857611';
        $amount = 10.2;

        $payment = $this->createPayment($variableSymbol);
        $this->paymentsRepository->update($payment, array(
            'amount' => $amount,
            'status' => PaymentsRepository::STATUS_REFUND,
            'created_at' => new DateTime('23 days ago')
        ));

        $mailContent = new MailContent();
        $mailContent->setAmount($amount);
        $mailContent->setTransactionDate(strtotime('2.3.2015 13:43'));
        $mailContent->setVs($variableSymbol);

        $result = $this->mailProcessor->processMail($mailContent, new TestOutput());
        $this->assertFalse($result);

        $log = $this->parsedMailLogsRepository->lastLog();
        $this->assertEquals(ParsedMailLogsRepository::STATE_ALREADY_REFUNDED, $log->state);
    }
}
