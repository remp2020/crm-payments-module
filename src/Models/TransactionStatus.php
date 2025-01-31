<?php

namespace Crm\PaymentsModule\Models;

class TransactionStatus
{
    public function __construct(
        public readonly bool $isSuccessful,
        public readonly ?string $status,
        public readonly ?string $message,
    ) {
    }
}
