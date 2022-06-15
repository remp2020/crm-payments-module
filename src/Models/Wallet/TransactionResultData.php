<?php

namespace Crm\PaymentsModule\Models\Wallet;

use Nette\Utils\Json;
use Tracy\Debugger;
use Tracy\ILogger;

class TransactionResultData
{
    private int $processingId;

    private TransactionStatus $status;

    private int $transactionId;

    private ?string $authorizationCode;

    private ?string $responseCode;

    private ?string $tdsRedirectionFormHtml;

    public function __construct(
        int $processingId,
        TransactionStatus $status,
        int $transactionId,
        ?string $authorizationCode,
        ?string $responseCode,
        ?string $tdsRedirectionFormHtml = null
    ) {
        $this->processingId = $processingId;
        $this->status = $status;
        $this->transactionId = $transactionId;
        $this->authorizationCode = $authorizationCode;
        $this->responseCode = $responseCode;
        $this->tdsRedirectionFormHtml = $tdsRedirectionFormHtml;
    }

    /**
     * @throws UnknownTransactionStatus
     */
    public static function fromPayload(string $payload): ?TransactionResultData
    {
        try {
            $data = Json::decode($payload, Json::FORCE_ARRAY);
        } catch (\Nette\Utils\JsonException $jsonException) {
            Debugger::log("Error decoding json: " . $jsonException->getMessage(), ILogger::ERROR);
            return null;
        }

        return new TransactionResultData(
            $data['processingId'],
            new TransactionStatus($data['status']),
            $data['transactionId'],
            $data['transactionData']['authorizationCode'] ?? null,
            $data['transactionData']['responseCode'] ?? null,
            $data['tdsRedirectionFormHtml'] ?? null
        );
    }

    public function getProcessingId(): int
    {
        return $this->processingId;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function getTransactionId(): int
    {
        return $this->transactionId;
    }

    public function getAuthorizationCode(): ?string
    {
        return $this->authorizationCode;
    }

    public function getResponseCode(): ?string
    {
        return $this->responseCode;
    }

    public function getTdsRedirectionFormHtml(): ?string
    {
        return $this->tdsRedirectionFormHtml;
    }
}
