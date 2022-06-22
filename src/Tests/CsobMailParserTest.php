<?php

namespace Crm\PaymentsModule\Tests;

use Crm\PaymentsModule\MailParser\CsobMailParser;

class CsobMailParserTest extends PaymentsTestCase
{
    public function testSingleTransferPayment()
    {
        $email = 'Vážený kliente,

dne 25.9.2018 byla na účtu 123456789 zaúčtovaná transakce typu: Došlá platba.

Název smlouvy: CRM International a.s.
Číslo smlouvy: 87654321
Majitel smlouvy: Shmelina a.s.
Účet: 123456789, CZK, CRM INTERNATION
Částka: +1 234,56 CZK
Účet protistrany: 1122334455/9999
Název protistrany: Capi Hnizdo a.s.
Variabilní symbol: 23456789
Konstantní symbol: 3456

Zůstatek na účtu po zaúčtování transakce: +1 234 567,89 CZK.

S přáním krásného dne
Vaše ČSOB
';
        $csobMailParser = new CsobMailParser();
        $mailContents = $csobMailParser->parse($email);

        $this->assertCount(1, $mailContents);

        $mailContent = $mailContents[0];
        $this->assertEquals('123456789', $mailContent->getAccountNumber());
        $this->assertEquals('CZK', $mailContent->getCurrency());
        $this->assertEquals(1234.56, $mailContent->getAmount());
        $this->assertEquals('23456789', $mailContent->getVs());
        $this->assertEquals('3456', $mailContent->getKs());
        $this->assertNull($mailContent->getSs());
        $this->assertEquals(strtotime('25.9.2018'), $mailContent->getTransactionDate());
    }

    // zaúčtovaná changed to zaúčtována
    public function testSingleTransferPaymentFixedTypoZauctovana()
    {
        $email = 'Vážený kliente,

dne 25.9.2018 byla na účtu 123456789 zaúčtována transakce typu: Došlá platba.

Název smlouvy: CRM International a.s.
Číslo smlouvy: 87654321
Majitel smlouvy: Shmelina a.s.
Účet: 123456789, CZK, CRM INTERNATION
Částka: +1 234,56 CZK
Účet protistrany: 1122334455/9999
Název protistrany: Capi Hnizdo a.s.
Variabilní symbol: 23456789
Konstantní symbol: 3456

Zůstatek na účtu po zaúčtování transakce: +1 234 567,89 CZK.

S přáním krásného dne
Vaše ČSOB
';
        $csobMailParser = new CsobMailParser();
        $mailContents = $csobMailParser->parse($email);

        $this->assertCount(1, $mailContents);

        $mailContent = $mailContents[0];
        $this->assertEquals('123456789', $mailContent->getAccountNumber());
        $this->assertEquals('CZK', $mailContent->getCurrency());
        $this->assertEquals(1234.56, $mailContent->getAmount());
        $this->assertEquals('23456789', $mailContent->getVs());
        $this->assertEquals('3456', $mailContent->getKs());
        $this->assertNull($mailContent->getSs());
        $this->assertEquals(strtotime('25.9.2018'), $mailContent->getTransactionDate());
    }

    public function testMultiTransferPayment()
    {
        $email = 'Vážený kliente,

dne 25.9.2018 byla na účtu 123456789 zaúčtovaná transakce typu: Došlá platba.

Název smlouvy: CRM International a.s.
Číslo smlouvy: 87654321
Majitel smlouvy: Shmelina a.s.
Účet: 123456789, CZK, CRM INTERNATION
Částka: +1 234,56 CZK
Účet protistrany: 1122334455/9999
Název protistrany: Capi Hnizdo a.s.
Variabilní symbol: 23456789
Konstantní symbol: 3456

Zůstatek na účtu po zaúčtování transakce: +1 234 567,89 CZK.

dne 25.9.2018 byla na účtu 123456789 zaúčtovaná transakce typu: Došlá platba.

Název smlouvy: CRM International a.s.
Číslo smlouvy: 87654321
Majitel smlouvy: Shmelina a.s.
Účet: 123456789, CZK, CRM INTERNATION
Částka: +987,65 CZK
Účet protistrany: 9988776655/1111
Název protistrany: Sorry jako a.s.
Variabilní symbol: 78787878
Konstantní symbol: 6789

Zůstatek na účtu po zaúčtování transakce: +1 235 555,54 CZK.

S přáním krásného dne
Vaše ČSOB
';
        $csobMailParser = new CsobMailParser();
        $mailContents = $csobMailParser->parse($email);

        $this->assertCount(2, $mailContents);

        $mailContent = $mailContents[0];
        $this->assertEquals('123456789', $mailContent->getAccountNumber());
        $this->assertEquals('CZK', $mailContent->getCurrency());
        $this->assertEquals(1234.56, $mailContent->getAmount());
        $this->assertEquals('23456789', $mailContent->getVs());
        $this->assertEquals('3456', $mailContent->getKs());
        $this->assertNull($mailContent->getSs());
        $this->assertEquals(strtotime('25.9.2018'), $mailContent->getTransactionDate());

        $mailContent = $mailContents[1];
        $this->assertEquals('123456789', $mailContent->getAccountNumber());
        $this->assertEquals('CZK', $mailContent->getCurrency());
        $this->assertEquals(987.65, $mailContent->getAmount());
        $this->assertEquals('78787878', $mailContent->getVs());
        $this->assertEquals('6789', $mailContent->getKs());
        $this->assertNull($mailContent->getSs());
        $this->assertEquals(strtotime('25.9.2018'), $mailContent->getTransactionDate());
    }

    public function testSingleCardpaySettlement()
    {
        $email = 'Vážený kliente,

dne 25.9.2018 byla na účtu 123456789 zaúčtována transakce platební kartou.

Název smlouvy: CRM International a.s.
Číslo smlouvy: 87654321
Majitel smlouvy: Shmelina a.s.
Účet: 123456789, CZK, CRM INTERNATION
Částka: +1 234,56 CZK
Z účtu: /
Variabilní symbol: 23456789
Konstantní symbol: 3456
Specifický symbol: 4545454545

Zůstatek na účtu po zaúčtování transakce: +1 234 567,89 CZK.

S přáním krásného dne
Vaše ČSOB
';
        $csobMailParser = new CsobMailParser();
        $mailContents = $csobMailParser->parse($email);

        $this->assertCount(0, $mailContents);
    }


    public function testErrorEmail()
    {
        $email = 'Specifický symbol: 4545454545

Zůstatek na účtu po zaúčtování transakce: +1 234 567,89 CZK.

S přáním krásného dne
Vaše ČSOB
';
        $csobMailParser = new CsobMailParser();
        $mailContents = $csobMailParser->parse($email);

        $this->assertCount(0, $mailContents);
    }
}
