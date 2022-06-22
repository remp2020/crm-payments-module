<?php

namespace Crm\PaymentsModule\MailParser;

use Tomaj\BankMailsParser\MailContent;
use Tomaj\BankMailsParser\Parser\ParserInterface;

class CsobMailParser implements ParserInterface
{
    /**
     * @param $content
     * @return MailContent[]
     */
    public function parse($content): array
    {
        $transactions = array_slice(explode("dne ", $content), 1);

        $mailContents = [];
        foreach ($transactions as $transaction) {
            $mailContent = new MailContent();

            $pattern1 = '/(.*) byla na účtu ([a-zA-Z0-9]+) (?:zaúčtovaná|zaúčtována) transakce typu: (Došlá platba|Příchozí úhrada|Došlá úhrada)/m';
            $res = preg_match($pattern1, $transaction, $result);
            if (!$res) {
                continue;
            }

            $mailContent->setTransactionDate(strtotime($result[1]));
            $mailContent->setAccountNumber($result[2]);

            $pattern2 = '/Částka: ([+-])(.*?) ([A-Z]+)/m';
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

            $pattern3 = '/Zpráva příjemci: (.*)/m';
            $res = preg_match($pattern3, $transaction, $result);
            if ($res) {
                $mailContent->setReceiverMessage(trim($result[1]));
            }

            $pattern4 = '/Variabilní symbol: ([0-9]{1,10})/m';
            $res = preg_match($pattern4, $transaction, $result);
            if ($res) {
                $mailContent->setVs($result[1]);
            }

            $pattern5 = '/Konstantní symbol: ([0-9]{1,10})/m';
            $res = preg_match($pattern5, $transaction, $result);
            if ($res) {
                $mailContent->setKs($result[1]);
            }

            $mailContents[] = $mailContent;
        }

        return $mailContents;
    }
}
