<?php

namespace Crm\PaymentsModule\Tests;

class TestPaymentConfig //implements PaymentConfig
{
    public function getSignature()
    {
        // toto by trebalo zmenit
        return "7a50706e656f63436d516a37435f37497848634d767a69597935414f5a42434e";
    }
}
