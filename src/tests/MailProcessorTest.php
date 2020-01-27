<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\MailConfirmation\MailProcessor;
use Tomaj\BankMailsParser\MailContent;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use DateTime;

class MailProcessorTest extends PaymentsTestCase
{
    /** @var  MailProcessor */
    private $mailProcessor;

    /** @var  ParsedMailLogsRepository */
    private $parsedMailLogsRepository;

    public function setup(): void
    {
        parent::setup();

        $this->mailProcessor = $this->container->getByType('Crm\PaymentsModule\MailConfirmation\MailProcessor');
        $this->parsedMailLogsRepository = $this->container->getByType('Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository');
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
        $this->assertTrue($result);

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
        $this->paymentsRepository->update($payment, array(
            'amount' => 10.4,
            'status' => PaymentsRepository::STATUS_PAID,
            'created_at' => new DateTime('23 days ago')
        ));

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
    }
}
