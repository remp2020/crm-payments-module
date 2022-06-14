<?php

namespace Crm\PaymentsModule\Models\Wallet;

use Crm\ApplicationModule\Config\ApplicationConfig;

class CardPayDirectService
{
    private ApplicationConfig $applicationConfig;

    private ?CardPayDirect $cardPayDirect = null;

    public function __construct(ApplicationConfig $applicationConfig)
    {
        $this->applicationConfig = $applicationConfig;
    }

    public function enableDebug(): void
    {
        $this->getCardPayDirect()->enableDebug();
    }

    public function postTransaction(TransactionPayload $payload): TransactionResult
    {
        return $this->getCardPayDirect()->postTransaction($payload);
    }

    public function checkTransaction(int $processingId, string $merchantId): TransactionResult
    {
        return $this->getCardPayDirect()->checkTransaction($processingId, $merchantId);
    }

    private function getCardPayDirect(): CardPayDirect
    {
        if (!$this->cardPayDirect) {
            $this->cardPayDirect = new CardPayDirect($this->getSecretKey());
        }
        return $this->cardPayDirect;
    }

    private function getSecretKey(): string
    {
        return $this->applicationConfig->get('cardpay_sharedsecret');
    }
}
