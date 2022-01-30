<?php

namespace Crm\PaymentsModule\MailParser;

use Tomaj\BankMailsParser\MailContent;
use Tomaj\BankMailsParser\Parser\ParserInterface;

class SkCsobMailParser implements ParserInterface
{
    /**
     * @param $content
     * @return MailContent[]
     */
    public function parse($content): array
    {
        $transactions = array_slice(explode("dňa ", $content), 1);

        $mailContents = [];
        foreach ($transactions as $transaction) {
            $mailContent = new MailContent();

            $pattern1 = '/(.*) bola na účte (.*) zaúčtovaná suma SEPA platobného príkazu/m';
            $res = preg_match($pattern1, $transaction, $result);
            if (!$res) {
                continue;
            }

            $mailContent->setTransactionDate(strtotime($result[1]));

            $pattern2 = '/suma:.*?([+-])(.*?) ([A-Z]+)/m';
            $res = preg_match($pattern2, $transaction, $result);
            if ($res) {
                // there's unicode non-breaking space (u00A0) in mime encoded version of email, unicode regex switched is necessary
                $amount = floatval(str_replace(',', '.', preg_replace('/\s+/u', '', $result[2])));
                $currency = $result[3];
                if ($result[1] === '-') {
                    $amount = -$amount;
                }
                $mailContent->setAmount($amount);
                $mailContent->setCurrency($currency);
            }

            $pattern3 = '/informácia pre príjemcu: (.*)/m';
            $res = preg_match($pattern3, $transaction, $result);
            if ($res) {
                $mailContent->setReceiverMessage(trim($result[1]));
            }

            $pattern4 = '/VS([0-9]+)/m';
            $res = preg_match($pattern4, $transaction, $result);
            if ($res) {
                $mailContent->setVs($result[1]);
            }

            $pattern5 = '/KS([0-9]+)/m';
            $res = preg_match($pattern5, $transaction, $result);
            if ($res) {
                $mailContent->setKs($result[1]);
            }

            $pattern6 = '/z účtu:.*?([A-Z0-9 ]+)/m';
            $res = preg_match($pattern6, $transaction, $result);
            if ($res) {
                $iban = trim($result[1]);
                $mailContent->setAccountNumber($iban);
            }

            $mailContents[] = $mailContent;
        }

        return $mailContents;
    }
}
