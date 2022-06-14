<?php

namespace Crm\PaymentsModule\Models\Wallet;

class TransactionResult
{
    const SUCCESS = 'success';
    const ERROR = 'error';

    private string $status;

    private ?string $message;

    private ?TransactionResultData $transactionResultData;

    public function __construct(string $status, ?string $message, ?TransactionResultData $transactionResultData = null)
    {
        $this->status = $status;
        $this->message = $message;
        $this->transactionResultData = $transactionResultData;
    }

    public function isSuccess(): bool
    {
        return $this->status == self::SUCCESS;
    }

    public function message(): ?string
    {
        return $this->message;
    }

    public function resultData(): ?TransactionResultData
    {
        return $this->transactionResultData;
    }
}
