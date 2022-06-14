<?php

namespace Crm\PaymentsModule\Models\Wallet;

class TdsData
{
    private ?string $cardholder = null;

    private ?string $email = null;

    private ?string $mobilePhone = null;

    private ?string $billingCity = null;

    private ?string $billingCountry = null;

    private ?string $billingAddress1 = null;

    private ?string $billingAddress2 = null;

    private ?string $billingZip = null;

    private ?string $shippingCity = null;

    private ?string $shippingCountry = null;

    private ?string $shippingAddress1 = null;

    private ?string $shippingAddress2 = null;

    private ?string $shippingZip = null;

    private ?bool $billingShippingMatch = null;

    public function isEntered(): bool
    {
        return $this->cardholder !== null ||
            $this->email !== null ||
            $this->mobilePhone !== null ||
            $this->billingCity !== null ||
            $this->billingAddress1 !== null ||
            $this->billingAddress2 !== null ||
            $this->billingZip !== null ||
            $this->shippingCity !== null ||
            $this->shippingCountry !== null ||
            $this->shippingAddress1 !== null ||
            $this->shippingAddress2 !== null ||
            $this->shippingZip !== null ||
            $this->billingShippingMatch !== null;
    }

    public function getData(): array
    {
        $data = [];

        if ($this->cardholder !== null) {
            $data['cardholder'] = $this->cardholder;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
        }
        if ($this->mobilePhone !== null) {
            $data['mobilePhone'] = $this->mobilePhone;
        }
        if ($this->billingCity !== null) {
            $data['billingCity'] = $this->billingCity;
        }
        if ($this->billingAddress1 !== null) {
            $data['billingAddress1'] = $this->billingAddress1;
        }
        if ($this->billingAddress2 !== null) {
            $data['billingAddress2'] = $this->billingAddress2;
        }
        if ($this->billingZip !== null) {
            $data['billingZip'] = $this->billingZip;
        }
        if ($this->shippingCity !== null) {
            $data['shippingCity'] = $this->shippingCity;
        }
        if ($this->shippingCountry !== null) {
            $data['shippingCountry'] = $this->shippingCountry;
        }
        if ($this->shippingAddress1 !== null) {
            $data['shippingAddress1'] = $this->shippingAddress1;
        }
        if ($this->shippingAddress2 !== null) {
            $data['shippingAddress2'] = $this->shippingAddress2;
        }
        if ($this->shippingZip !== null) {
            $data['shippingZip'] = $this->shippingZip;
        }
        if ($this->billingShippingMatch !== null) {
            $data['billingShippingMatch'] = $this->billingShippingMatch;
        }

        return $data;
    }

    public function getCardholder(): ?string
    {
        return $this->cardholder;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setCardholder(?string $cardholder): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $cardholder))) {
            throw new WrongTransactionPayloadData("Wrong card holder format '{$cardholder}'");
        }

        $this->cardholder = $cardholder;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setEmail(?string $email): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,254}$/", $email))) {
            throw new WrongTransactionPayloadData("Wrong email format '{$email}'");
        }

        $this->email = $email;
        return $this;
    }

    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setMobilePhone(?string $mobilePhone): self
    {
        if (empty(preg_match("/^\d{1,3}-\d{1,15}$/", $mobilePhone))) {
            throw new WrongTransactionPayloadData("Wrong mobile phone format '{$mobilePhone}'. Valid format is PREFIX-NUMBER");
        }

        $this->mobilePhone = $mobilePhone;
        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setBillingCity(?string $billingCity): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $billingCity))) {
            throw new WrongTransactionPayloadData("Wrong billing city format '{$billingCity}'");
        }

        $this->billingCity = $billingCity;
        return $this;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setBillingCountry(?string $billingCountry): self
    {
        if (empty(preg_match("/^\d{3}$/", $billingCountry))) {
            throw new WrongTransactionPayloadData("Wrong billing country format '{$billingCountry}'");
        }

        $this->billingCountry = $billingCountry;
        return $this;
    }

    public function getBillingAddress1(): ?string
    {
        return $this->billingAddress1;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setBillingAddress1(?string $billingAddress1): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $billingAddress1))) {
            throw new WrongTransactionPayloadData("Wrong billing address format '{$billingAddress1}'");
        }

        $this->billingAddress1 = $billingAddress1;
        return $this;
    }

    public function getBillingAddress2(): ?string
    {
        return $this->billingAddress2;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setBillingAddress2(?string $billingAddress2): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $billingAddress2))) {
            throw new WrongTransactionPayloadData("Wrong billing address 2 format '$billingAddress2}'");
        }

        $this->billingAddress2 = $billingAddress2;
        return $this;
    }

    public function getBillingZip(): ?string
    {
        return $this->billingZip;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setBillingZip(?string $billingZip): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,16}$/", $billingZip))) {
            throw new WrongTransactionPayloadData("Wrong billing zip '$billingZip}'");
        }

        $this->billingZip = $billingZip;
        return $this;
    }

    public function getShippingCity(): ?string
    {
        return $this->shippingCity;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setShippingCity(?string $shippingCity): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $shippingCity))) {
            throw new WrongTransactionPayloadData("Wrong shipping city format '$shippingCity}'");
        }

        $this->shippingCity = $shippingCity;
        return $this;
    }

    public function getShippingCountry(): ?string
    {
        return $this->shippingCountry;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setShippingCountry(?string $shippingCountry): self
    {
        if (empty(preg_match("/^\d{3}$/", $shippingCountry))) {
            throw new WrongTransactionPayloadData("Wrong shipping country format '$shippingCountry}'");
        }

        $this->shippingCountry = $shippingCountry;
        return $this;
    }

    public function getShippingAddress1(): ?string
    {
        return $this->shippingAddress1;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setShippingAddress1(?string $shippingAddress1): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $shippingAddress1))) {
            throw new WrongTransactionPayloadData("Wrong shipping address format '$shippingAddress1}'");
        }

        $this->shippingAddress1 = $shippingAddress1;
        return $this;
    }

    public function getShippingAddress2(): ?string
    {
        return $this->shippingAddress2;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setShippingAddress2(?string $shippingAddress2): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,50}$/", $shippingAddress2))) {
            throw new WrongTransactionPayloadData("Wrong shipping address 2 format '$shippingAddress2}'");
        }

        $this->shippingAddress2 = $shippingAddress2;
        return $this;
    }

    public function getShippingZip(): ?string
    {
        return $this->shippingZip;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setShippingZip(?string $shippingZip): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,16}$/", $shippingZip))) {
            throw new WrongTransactionPayloadData("Wrong shipping zip format '$shippingZip}'");
        }

        $this->shippingZip = $shippingZip;
        return $this;
    }

    public function getBillingShippingMatch(): ?bool
    {
        return $this->billingShippingMatch;
    }

    public function setBillingShippingMatch(bool $billingShippingMatch): self
    {
        $this->billingShippingMatch = $billingShippingMatch;
        return $this;
    }
}
