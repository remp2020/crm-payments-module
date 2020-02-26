<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\MailParser\TatraBankaMailDecryptor;
use Crm\PaymentsModule\MailParser\TatraBankaStatementMailParser;

class TatraBankaStatementMailParserTest extends PaymentsTestCase
{
    /**
     * TODO: this causes phpunit test to crash with ErrorException: Allowed memory size when run together with other tests, try to fix
     * @group memory-exhaustive
     */
    public function testTransferPayments()
    {
        $email = file_get_contents(__DIR__ . '/data/tb_encrypted_mail_body.txt');

        $parser = new TatraBankaStatementMailParser(
            new TatraBankaMailDecryptor(
                __DIR__ . '/data/tb_mail_private_key.asc',
                'heslo'
            )
        );
        $mailContents = $parser->parse($email);

        $this->assertCount(4, $mailContents);

        $mailContent = $mailContents[0];
        $this->assertEquals('2621234415', $mailContent->getAccountNumber());
        $this->assertEquals(13.37, $mailContent->getAmount());
        $this->assertEquals('4169344603', $mailContent->getVs());
        $this->assertEquals(strtotime('20190315'), $mailContent->getTransactionDate());

        $mailContent = $mailContents[1];
        $this->assertEquals('2621234415', $mailContent->getAccountNumber());
        $this->assertEquals(15.0, $mailContent->getAmount());
        $this->assertEquals('9051230034', $mailContent->getVs());
        $this->assertEquals(strtotime('20190315'), $mailContent->getTransactionDate());

        $mailContent = $mailContents[2];
        $this->assertEquals('2946123663', $mailContent->getAccountNumber());
        $this->assertEquals(59.9, $mailContent->getAmount());
        $this->assertEquals('9101235209', $mailContent->getVs());
        $this->assertEquals(strtotime('20190315'), $mailContent->getTransactionDate());

        $mailContent = $mailContents[3];
        $this->assertEquals('2912329663', $mailContent->getAccountNumber());
        $this->assertEquals(34.9, $mailContent->getAmount());
        $this->assertEquals('9101955247', $mailContent->getVs());
        $this->assertEquals(strtotime('20190315'), $mailContent->getTransactionDate());
    }
}
