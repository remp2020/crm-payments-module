<?php

namespace Crm\PaymentsModule\MailParser;

use Tomaj\BankMailsParser\MailContent;
use Tomaj\BankMailsParser\Parser\ParserInterface;

class CsobMailParser implements ParserInterface
{
    /**
     * @return MailContent[]
     */
    public function parseMulti(string $content): array
    {
        $transactions = array_slice(explode("dne ", $content), 1);

        $mailContents = [];
        foreach ($transactions as $transaction) {
            $mailContent = $this->parse($transaction);
            if ($mailContent !== null) {
                $mailContents[] = $mailContent;
            }
        }

        return $mailContents;
    }

    public function parse(string $content): ?MailContent
    {
        $mailContent = new MailContent();

        $pattern1 = '/(.*) byl(?:a)? na účtu ([a-zA-Z0-9]+) (?:zaúčtovaná|zaúčtována|zaúčtovaný) (?:transakce typu: )?(Došlá platba|Příchozí úhrada|Došlá úhrada|SEPA převod)/m';
        $res = preg_match($pattern1, $content, $result);
        if (!$res) {
            return null;
        }

        $mailContent->setTransactionDate(strtotime($result[1]));
        $mailContent->setAccountNumber($result[2]);

        $pattern2 = '/Částka: ([+-])(.*?) ([A-Z]+)/m';
        $res = preg_match($pattern2, $content, $result);
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

        $pattern3 = '/Zpráva příjemci: (.*)/m';
        $res = preg_match($pattern3, $content, $result);
        if ($res) {
            $mailContent->setReceiverMessage(trim($result[1]));
        }

        $pattern4 = '/Variabilní symbol: ([0-9]{1,10})/m';
        $res = preg_match($pattern4, $content, $result);
        if ($res) {
            $mailContent->setVs($result[1]);
        }
        $pattern4 = '/Identifikace: ([0-9]{1,10})/m';
        $res = preg_match($pattern4, $content, $result);
        if ($res) {
            $mailContent->setVs($result[1]);
        }
        $pattern4 = '/Účel platby: ([0-9]{1,10})/m';
        $res = preg_match($pattern4, $content, $result);
        if ($res) {
            $mailContent->setVs($result[1]);
        }

        $pattern5 = '/Konstantní symbol: ([0-9]{1,10})/m';
        $res = preg_match($pattern5, $content, $result);
        if ($res) {
            $mailContent->setKs($result[1]);
        }

        return $mailContent;
    }
}
