<?php

namespace Crm\PaymentsModule\Models\Wallet;

class TransactionStatus
{
    const OK = 'OK';
    const FAIL = 'FAIL';
    const TDS_AUTH_REQUIRED = 'TDS_AUTH_REQUIRED';

    private string $status;

    /**
     * @throws UnknownTransactionStatus
     */
    public function __construct(string $status)
    {
        if (!self::isValidStatus($status)) {
            throw new UnknownTransactionStatus("Unknown status '{$status}'");
            ;
        }

        $this->status = $status;
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::validStatuses(), true);
    }

    public static function validStatuses(): array
    {
        return [self::OK, self::FAIL, self::TDS_AUTH_REQUIRED];
    }

    public function isOk(): bool
    {
        return $this->status == self::OK;
    }

    public function isFail(): bool
    {
        return $this->status == self::FAIL;
    }

    public function isTdsRequired(): bool
    {
        return $this->status == self::TDS_AUTH_REQUIRED;
    }

    public function rawStatus(): string
    {
        return $this->status;
    }
}
