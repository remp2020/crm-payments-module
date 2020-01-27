<?php

namespace Crm\PaymentsModule\Tests;

class PaymentsRepositoryTest extends PaymentsTestCase
{
    public function testLoadVariableSymbolWithoutLeedingZeros()
    {
        $payment1 = $this->createPayment('0006789465');
        $payment2 = $this->createPayment('0106789465');
        $payment3 = $this->createPayment('4106789465');

        $p = $this->paymentsRepository->findByVs('6789465');

        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('06789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('006789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('0006789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment1->id);

        $p = $this->paymentsRepository->findByVs('106789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment2->id);

        $p = $this->paymentsRepository->findByVs('4106789465');
        $this->assertNotFalse($p);
        $this->assertEquals($p->id, $payment3->id);
    }
}
