<?php

namespace Crm\PaymentsModule\Models\Wallet;

use Nette\Utils\DateTime;
use Nette\Utils\Validators;

class TransactionPayload
{
    private string $merchantId;

    private int $amount;

    private string $currency;

    private ?string $variableSymbol;

    private ?string $e2eReference = null;

    private string $clientIpAddress;

    private string $clientName;

    private string $timestamp;

    private ?string $googlePayToken = null;

    private ?string $applePayToken = null;

    private bool $preAuthorization = false;

    private ?string $tdsTermUrl = null;

    private ?TdsData $tdsData = null;

    private ?IpspData $ipspData = null;

    public function __construct()
    {
        $this->timestamp = DateTime::from('now')->format('dmYHis');
    }

    public function getMerchantId(): string
    {
        return $this->merchantId;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setMerchantId(string $merchantId): self
    {
        if (empty(preg_match("/^\d{3,5}$/", $merchantId))) {
            throw new WrongTransactionPayloadData("Wrong merchantId format '{$merchantId}'");
        }
        $this->merchantId = $merchantId;
        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setAmount(int $amount): self
    {
        if ($amount < 1 || $amount > 99999999999) {
            throw new WrongTransactionPayloadData("Wrong amount '{$amount}'");
        }
        $this->amount = $amount;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setCurrency(int $currency): self
    {
        if (empty(preg_match('/^\d{3}$/', $currency))) {
            throw new WrongTransactionPayloadData("Wrong currency format '{$currency}'");
        }
        $this->currency = $currency;
        return $this;
    }

    public function getVariableSymbol(): ?string
    {
        return $this->variableSymbol;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setVariableSymbol(string $variableSymbol): self
    {
        if (empty(preg_match('/^\d{1,10}$/', $variableSymbol))) {
            throw new WrongTransactionPayloadData("Wrong variable symbol format '{$variableSymbol}'");
        }
        $this->variableSymbol = $variableSymbol;
        return $this;
    }

    public function getE2eReference(): ?string
    {
        return $this->e2eReference;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setE2eReference(string $e2eReference): self
    {
        if (empty(preg_match('/^[a-zA-Z\d\/?.,:()\' +-]{1,35}$/', $e2eReference))) {
            throw new WrongTransactionPayloadData("Wrong e2eReference format '{e2eReference}'");
        }
        $this->e2eReference = $e2eReference;
        return $this;
    }

    public function getClientIpAddress(): string
    {
        return $this->clientIpAddress;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setClientIpAddress(string $clientIpAddress): self
    {
        if (strlen($clientIpAddress) < 1 || strlen($clientIpAddress) > 45) {
            throw new WrongTransactionPayloadData("Wrong clientIpAdress format '{$clientIpAddress}'");
        }
        $this->clientIpAddress = $clientIpAddress;
        return $this;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setClientName(string $clientName): self
    {
        if (empty(preg_match('/^[a-zA-Z\d.@_ -]{1,64}$/', $clientName))) {
            throw new WrongTransactionPayloadData("Wrong clientName format '{$clientName}'");
        }
        $this->clientName = $clientName;
        return $this;
    }

    public function getGooglePayToken(): ?string
    {
        return $this->googlePayToken;
    }

    public function setGooglePayToken(string $googlePayToken): self
    {
        $this->googlePayToken = $googlePayToken;
        return $this;
    }

    public function getApplePayToken(): ?string
    {
        return $this->applePayToken;
    }

    public function setApplePayToken(string $applePayToken): self
    {
        $this->applePayToken = $applePayToken;
        return $this;
    }

    public function setIsPreAuthorization(bool $preAuthorization): self
    {
        $this->preAuthorization = $preAuthorization;
        return $this;
    }

    public function isPreAuthorization(): bool
    {
        return $this->preAuthorization;
    }

    public function getTdsTermUrl(): ?string
    {
        return $this->tdsTermUrl;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setTdsTermUrl(string $tdsTermUrl): self
    {
        if (!Validators::isUrl($tdsTermUrl)) {
            throw new WrongTransactionPayloadData("Wrong url format '{$tdsTermUrl}'");
        }

        $this->tdsTermUrl = $tdsTermUrl;
        return $this;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function getTdsData(): ?TdsData
    {
        return $this->tdsData;
    }

    public function setTdsData(TdsData $tdsData): self
    {
        $this->tdsData = $tdsData;
        return $this;
    }

    public function getIpsData(): ?IpspData
    {
        return $this->ipspData;
    }

    public function setIpsData(IpspData $ipspData): self
    {
        $this->ipspData = $ipspData;
        return $this;
    }
}
