<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\PaymentsModule\Builder\ParsedMailLogsBuilder;
use Crm\PaymentsModule\Gateways\GatewayAbstract;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use DateInterval;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\DateTime;
use Omnipay\Common\Exception\InvalidRequestException;
use Symfony\Component\Console\Output\OutputInterface;
use Tomaj\BankMailsParser\MailContent;
use Tracy\Debugger;
use Tracy\ILogger;

class MailProcessor
{
    private ParsedMailLogsBuilder $logBuilder;

    private MailContent $mailContent;

    private OutputInterface $output;

    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private PaymentProcessor $paymentProcessor,
        private ParsedMailLogsBuilder $parsedMailLogsBuilder,
        private ParsedMailLogsRepository $parsedMailLogsRepository
    ) {
    }

    public function processMail(MailContent $mailContent, OutputInterface $output, $skipCheck = false)
    {
        $this->mailContent = $mailContent;
        $this->output = $output;

        if ($this->mailContent->getSign() == null) {
            $transactionDatetime = new DateTime('@' . $this->mailContent->getTransactionDate());
            $this->logBuilder = $this->parsedMailLogsBuilder->createNew()
                ->setDeliveredAt($transactionDatetime);
            return $this->processBankAccountMovements();
        }

        $transactionDatetime = DateTime::createFromFormat('dmYHis', $this->mailContent->getTransactionDate());
        $this->logBuilder = $this->parsedMailLogsBuilder->createNew()
            ->setDeliveredAt($transactionDatetime);
        return $this->processCardMovements($skipCheck);
    }

    private function processBankAccountMovements()
    {
        if ($this->mailContent->getTransactionDate()) {
            $transactionDate = DateTime::from($this->mailContent->getTransactionDate());
        } else {
            $transactionDate = new DateTime();
        }
        $this->output->writeln(" * Parsed email <info>{$transactionDate->format('d.m.Y H:i')}</info>");
        $this->output->writeln("    -> VS - <info>{$this->mailContent->getVS()}</info> {$this->mailContent->getAmount()} {$this->mailContent->getCurrency()}");

        $this->logBuilder
            ->setMessage($this->mailContent->getReceiverMessage())
            ->setAmount($this->mailContent->getAmount());

        $payment = $this->getPaymentFromMailContentVs();
        if (!$payment) {
            return false;
        }

        // Some mails (for failed payments) don't necessarily have to contain the amount. We can (should) ignore them.
        if ($this->mailContent->getAmount() === null) {
            return false;
        }

        if ($payment->amount != $this->mailContent->getAmount()) {
            $this->logBuilder
                ->setState(ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT)
                ->save();
        }

        // we will not approve payment when amount in email (real payment) is lower than payment (in db)
        if ($payment->amount > $this->mailContent->getAmount()) {
            return false;
        }

        $newPaymentThreshold = (clone $transactionDate)->sub(new DateInterval('P10D'));

        $createdNewPayment = false;

        if ($payment->status == PaymentsRepository::STATUS_PAID && $payment->created_at < $newPaymentThreshold) {
            try {
                $newPayment = $this->paymentsRepository->copyPayment($payment);
            } catch (\Exception $exception) {
                $this->output->writeln(" * Couldn't copy payment: <info>{$payment->id}</info>. Error: {$exception->getMessage()}");
                Debugger::log($exception, ILogger::EXCEPTION);
                return false;
            }

            $payment = $newPayment;
            $this->logBuilder->setPayment($payment);
            $createdNewPayment = true;
        }

        $duplicatedPaymentCheck = $this->parsedMailLogsRepository
            ->findByVariableSymbols([$payment->variable_symbol])
            ->where('state = ?', ParsedMailLogsRepository::STATE_CHANGED_TO_PAID)
            ->where('created_at >= ?', $newPaymentThreshold)
            ->count('*');
        if ($duplicatedPaymentCheck > 0) {
            $this->logBuilder
                ->setState(ParsedMailLogsRepository::STATE_DUPLICATED_PAYMENT)
                ->save();
            return false;
        }

        if ($payment->status == PaymentsRepository::STATUS_PAID) {
            $this->logBuilder
                ->setState(ParsedMailLogsRepository::STATE_ALREADY_PAID)
                ->save();
            return false;
        }

        if ($payment->status == PaymentsRepository::STATUS_REFUND) {
            $this->logBuilder
                ->setState(ParsedMailLogsRepository::STATE_ALREADY_REFUNDED)
                ->save();
            return false;
        }

        if (in_array($payment->status, [PaymentsRepository::STATUS_FORM, PaymentsRepository::STATUS_FAIL, PaymentsRepository::STATUS_TIMEOUT], true)) {
            $this->paymentsRepository->updateStatus($payment, PaymentsRepository::STATUS_PAID, true);

            $state = ParsedMailLogsRepository::STATE_CHANGED_TO_PAID;
            if ($createdNewPayment) {
                $state = ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT;
            }
            $this->logBuilder->setState($state)->save();
        }

        return true;
    }

    private function processCardMovements($skipCheck)
    {
        $transactionDate = DateTime::createFromFormat('dmYHis', $this->mailContent->getTransactionDate());
        $date = $transactionDate->format('d.m.Y H:i');
        $this->output->writeln(" * Parsed email <info>{$date}</info>");
        $this->output->writeln("    -> VS - <info>{$this->mailContent->getVS()}</info> {$this->mailContent->getAmount()} {$this->mailContent->getCurrency()}");
        $fields = [''];

        $this->logBuilder
            ->setMessage($this->mailContent->getReceiverMessage())
            ->setAmount($this->mailContent->getAmount());

        $payment = $this->getPaymentFromMailContentVs();
        if (!$payment) {
            return false;
        }

        $sign = $this->mailContent->getSign();

        if (!$sign) {
            $this->output->writeln("    -> missing sign");
            $this->logBuilder->setState(ParsedMailLogsRepository::STATE_NO_SIGN);
            $this->logBuilder->save();
            return false;
        }
        $fields['SIGN'] = $sign;
        $fields['HMAC'] = $sign;
        $fields['AMT'] = $this->mailContent->getAmount();
        $fields['CURR'] = $this->mailContent->getCurrency();
        $fields['TIMESTAMP'] = $this->mailContent->getTransactionDate();
        $fields['CC'] = $this->mailContent->getCc();
        $fields['TID'] = $this->mailContent->getTid();
        $fields['VS'] = $payment->variable_symbol;
        $fields['AC'] = $this->mailContent->getAc();
        $fields['RES'] = $this->mailContent->getRes();
        $fields['RC'] = $this->mailContent->getRc();
        $fields['TXN'] = $this->mailContent->getTxn();

        if (!$skipCheck && $payment->status == PaymentsRepository::STATUS_PAID) {
            $this->logBuilder->setState(ParsedMailLogsRepository::STATE_ALREADY_PAID)->save();
            return false;
        }

        if (!$skipCheck && $payment->status == PaymentsRepository::STATUS_REFUND) {
            $this->logBuilder->setState(ParsedMailLogsRepository::STATE_ALREADY_REFUNDED)->save();
            return false;
        }

        if ($this->mailContent->getRes() !== 'OK') {
            $this->paymentsRepository->updateStatus(
                $payment,
                PaymentsRepository::STATUS_FAIL,
                false,
                "non-OK RES mail param: {$this->mailContent->getRes()}"
            );
            $this->output->writeln("    -> Payment has non-OK result, setting failed");
            return true;
        }

        $cid = $this->mailContent->getCid();

        if ($payment->payment_gateway->is_recurrent && !$cid) {
            // halt the processing, not a comfortpay confirmation email
            // we receive both cardpay and comfortpay notifications for payment at the same time
            $this->output->writeln("    -> Payment's [{$payment->id}] gateway is recurrent but CID was not received [{$cid}], halting");
            return false;
        }

        if ($cid) {
            $fields['CID'] = $cid;
            $fields['TRES'] = 'OK';
        }

        // TODO toto je ultra mega hack
        foreach ($fields as $k => $v) {
            $_GET[$k] = $v;
        }

        try {
            $this->paymentProcessor->complete($payment, function ($payment, GatewayAbstract $gateway) {
                if ($payment->status === PaymentsRepository::STATUS_PAID) {
                    $this->logBuilder->setState(ParsedMailLogsRepository::STATE_CHANGED_TO_PAID)->save();
                }
            });
        } catch (InvalidRequestException $exception) {
            $this->output->writeln(" * Couldn't complete payment: <info>{$payment->variable_symbol}</info>. Email validation failed.");
            Debugger::log("Couldn't complete mail processed payment: " . $exception->getMessage(), ILogger::ERROR);
            throw $exception;
        }

        return true;
    }

    private function getPaymentFromMailContentVs(): ?ActiveRow
    {
        $vs = $this->mailContent->getVs();
        if (!$vs) {
            // library for parsing mail content would find variable symbol if it is present in any field; this is error
            $this->logBuilder->setState(ParsedMailLogsRepository::STATE_WITHOUT_VS);
            $this->logBuilder->save();
            return null;
        }
        $this->logBuilder->setVariableSymbol($vs);

        $payment = $this->paymentsRepository->findLastByVS($vs);
        if ($payment) {
            $this->logBuilder->setPayment($payment);
            return $payment;
        }

        // If payment was not found with variable symbol returned by library, try to check receiver message.
        // Users sometimes enter incorrect value into creditor reference information (eg. missing last digit in "Referencia platitela")
        // but correct variable symbol into receiver message ("Informacia pre prijemcu").
        // Library parsing email content will return first number that looks like variable symbol
        // (there is not way for it to validate it against database).
        // We shouldn't be trying to match this, but we have around 2 payments each week with this issue ¯\_(ツ)_/¯

        $receiverMessage = $this->mailContent->getReceiverMessage();
        if ($receiverMessage === null) {
            // we found variable symbol above so we shouldn't log STATE_WITHOUT_VS
            // (this is just alternative search because we were unable to find payment for first VS)
            $this->logBuilder->setState(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND);
            $this->logBuilder->save();
            return null;
        }

        // VS number with prefix; one to 10 digits -> vs1 ... vs0123456789
        $pattern = '/vs[:\.\-_ ]??(\d{1,10})/i';
        $matched = preg_match($pattern, $receiverMessage, $result);
        if ($matched === false || !isset($result[1])) {
            // or try just 8 to 10 digits without prefix (01234567 - 0123456789)
            $pattern = '/(\d{8,10})/i';
            $matched = preg_match($pattern, $receiverMessage, $result);
            if ($matched === false || !isset($result[1])) {
                // we found variable symbol above so we shouldn't log STATE_WITHOUT_VS
                // (this is just alternative search because we were unable to find payment for first VS)
                $this->logBuilder->setState(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND);
                $this->logBuilder->save();
                return null;
            }
        }

        $vs = $result[1];

        $payment = $this->paymentsRepository->findLastByVS($vs);
        if ($payment) {
            $this->mailContent->setVs($vs);
            $this->logBuilder->setVariableSymbol($vs);
            $this->logBuilder->setPayment($payment);
            return $payment;
        }

        // we tried two different variable symbols, payment not found
        $this->logBuilder
            ->setState(ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND)
            ->save();
        return null;
    }
}
