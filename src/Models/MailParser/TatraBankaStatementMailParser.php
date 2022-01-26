<?php

namespace Crm\PaymentsModule\MailParser;

use Tomaj\BankMailsParser\MailContent;
use Tomaj\BankMailsParser\Parser\ParserInterface;

class TatraBankaStatementMailParser implements ParserInterface
{
    private $decryptor;

    public function __construct(
        TatraBankaMailDecryptor $decryptor
    ) {
        $this->decryptor = $decryptor;
    }

    /**
     * @param $content
     * @return MailContent[]|null
     */
    public function parse($content)
    {
        $mailContents = [];

        $results = [];
        $res = preg_match('/(-{5}BEGIN[A-Za-z0-9 \-\r?\n+\/=]+END PGP MESSAGE-{5})/m', $content, $results);
        if (!$res) {
            return null;
        }
        $content = $results[0];
        $content = $this->decryptor->decrypt($content);
        if (!$content) {
            return null;
        }

        $data = preg_split("/\r\n|\n|\r/", $content);

        foreach ($data as $line => $row) {
            if (!$line) {
                continue;
            }

            $cols = array_filter(explode('|', $row));
            if (empty($cols)) {
                continue;
            }

            $mailContent = new MailContent();
            $mailContent->setAmount(floatval($cols[9]));
            $mailContent->setCurrency($cols[10]);
            $mailContent->setVs($cols[18]);
            $mailContent->setAccountNumber(trim($cols[19]));
            $mailContent->setTransactionDate(strtotime($cols[0]));

            $mailContents[] = $mailContent;
        }

        return $mailContents;
    }
}
