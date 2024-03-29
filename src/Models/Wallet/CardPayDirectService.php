<?php

namespace Crm\PaymentsModule\Models\Wallet;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Psr\Log\LoggerInterface;

class CardPayDirectService
{
    private ApplicationConfig $applicationConfig;

    private ?CardPayDirect $cardPayDirect = null;

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, ApplicationConfig $applicationConfig)
    {
        $this->applicationConfig = $applicationConfig;
        $this->logger = $logger;
    }

    public function enableDebug(bool $enable = true): void
    {
        if ($enable) {
            $this->getCardPayDirect()->enableDebug();
        }
    }

    public function enableLoggingRequests(bool $enable = true): void
    {
        if ($enable) {
            $this->getCardPayDirect()->enableLoggingRequests();
        }
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
            $this->cardPayDirect = new CardPayDirect($this->getSecretKey(), $this->logger);
        }
        return $this->cardPayDirect;
    }

    private function getSecretKey(): string
    {
        return $this->applicationConfig->get('cardpay_sharedsecret');
    }
}
