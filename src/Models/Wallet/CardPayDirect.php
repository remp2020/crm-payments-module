<?php

namespace Crm\PaymentsModule\Models\Wallet;

use GuzzleHttp;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Nette\Utils\DateTime;

/**
 * This class is kept outside of CRM
 * It can be open-sourced as a part of payments package together with other classes
 * For use in CRM, take a loot at CardPayDirectService
 */
class CardPayDirect
{
    // to generate transaction detail url we can append `/{processigId}` to url
    private string $transactionUrl = 'https://moja.tatrabanka.sk/cgi-bin/e-commerce/start/api/cardpay/transaction';

    private string $debugUrl = 'https://platby.tomaj.sk/cgi-bin/e-commerce/start/api/cardpay/transaction';

    private bool $isDebug = false;

    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function enableDebug(): void
    {
        $this->isDebug = true;
    }

    private function getUrl(): string
    {
        if ($this->isDebug) {
            return $this->debugUrl;
        }
        return $this->transactionUrl;
    }

    public function postTransaction(TransactionPayload $payload): TransactionResult
    {
        $serializer = new PayloadSerializer();
        $data = $serializer->serialize($payload);

        $client = new GuzzleHttp\Client();
        try {
            $result = $client->request('POST', $this->getUrl(), [
                'headers' => [
                    'X-Merchant-Id' => $payload->getMerchantId(),
                    'Authorization' => 'HMAC=' . $this->sign($data),
                ],
                'body' => $data,
            ]);
        } catch (ServerException $serverException) {
            return new TransactionResult(
                TransactionResult::ERROR,
                "Server: " . $serverException->getMessage()
            );
        } catch (ClientException $clientException) {
            return new TransactionResult(
                TransactionResult::ERROR,
                "Client: " . $clientException->getMessage()
            );
        }

        $authorization = $result->getHeaderLine("Authorization");
        if (!$authorization) {
            // not sure about this, probably exception will be better here
            return new TransactionResult(
                TransactionResult::ERROR,
                "Wrong HMAC - missing authorization header",
            );
        }

        if (!$this->checkAuthorization($result->getBody()->getContents(), $authorization)) {
            // not sure about this, probably exception will be better here
            return new TransactionResult(
                TransactionResult::ERROR,
                "Wrong HMAC - sign error",
            );
        }

        return new TransactionResult(
            TransactionResult::SUCCESS,
            null,
            TransactionResultData::fromPayload($result->getBody())
        );

        // Odpoved
        // {"error":"Invalid authorization header"}
        // {"error":"Cannot process Google Pay token"}
    }

    public function checkTransaction(int $processingId, string $merchantId): TransactionResult
    {
        $url = $this->getUrl() . '/' . $processingId;

        $client = new GuzzleHttp\Client();

        $timestamp = DateTime::from('now')->format('dmYHis');

        $signData = "{$merchantId};{$timestamp};{$processingId}";

        try {
            $result = $client->request('GET', $url, [
                'headers' => [
                    'X-Merchant-Id' => $merchantId,
                    'X-Timestamp' => $timestamp,
                    'Authorization' => 'HMAC=' . $this->sign($signData),
                ],
            ]);
        } catch (ServerException $serverException) {
            return new TransactionResult(
                TransactionResult::ERROR,
                "Server: " . $serverException->getMessage()
            );
        } catch (ClientException $clientException) {
            return new TransactionResult(
                TransactionResult::ERROR,
                "Client: " . $clientException->getMessage()
            );
        }

        $authorization = $result->getHeaderLine("Authorization");
        if (!$authorization) {
            // not sure about this, probably exception will be better here
            return new TransactionResult(
                TransactionResult::ERROR,
                "Wrong HMAC - missing authorization header",
            );
        }

        if (!$this->checkAuthorization($result->getBody()->getContents(), $authorization)) {
            // not sure about this, probably exception will be better here
            return new TransactionResult(
                TransactionResult::ERROR,
                "Wrong HMAC - sign error",
            );
        }

        return new TransactionResult(
            TransactionResult::SUCCESS,
            null,
            TransactionResultData::fromPayload($result->getBody())
        );
    }

    private function checkAuthorization(string $content, string $authorizationHeader): bool
    {
        $hmac = $this->parseHmacFromHeader($authorizationHeader);
        $signed = $this->sign($content);
        return $signed === $hmac;
    }

    private function parseHmacFromHeader(string $authorizationHeader): ?string
    {
        $parts = explode(",", $authorizationHeader);
        $result = [];
        foreach ($parts as $part) {
            $kv = explode("=", $part);
            $result[trim($kv[0])] = trim($kv[1]);
        }
        if (isset($result["HMAC"])) {
            return $result["HMAC"];
        }

        return null;
    }

    private function sign(string $input)
    {
        $sharedSecret = pack('H*', $this->secretKey);
        return hash_hmac('sha256', $input, $sharedSecret);
    }
}
