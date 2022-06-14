<?php

namespace Crm\PaymentsModule\Models\Wallet;

/**
 * Data object as defined in CardPay Direct API documentation
 */
class IpspData
{
    private ?string $submerchantId = null;

    private ?string $name = null;

    private ?string $location = null;

    private ?string $country = null;

    public function isEntered(): bool
    {
        return $this->submerchantId !== null ||
            $this->name !== null ||
            $this->location !== null ||
            $this->country !== null;
    }

    public function getData(): array
    {
        $data = [];

        if ($this->submerchantId !== null) {
            $data['submerchantId'] = $this->submerchantId;
        }
        if ($this->name !== null) {
            $data['name'] = $this->name;
        }
        if ($this->location !== null) {
            $data['location'] = $this->location;
        }
        if ($this->country !== null) {
            $data['country'] = $this->country;
        }

        return $data;
    }

    public function getSubmerchantId(): ?string
    {
        return $this->submerchantId;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setSubmerchantId(?string $submerchantId): self
    {
        if (empty(preg_match("/^\d{1,15}$/", $submerchantId))) {
            throw new WrongTransactionPayloadData("Wrong submerchantId format '{$submerchantId}'");
        }

        $this->submerchantId = $submerchantId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setName(?string $name): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,25}$/", $name))) {
            throw new WrongTransactionPayloadData("Wrong name format '{$name}'");
        }

        $this->name = $name;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setLocation(?string $location): self
    {
        if (empty(preg_match("/^[ 0-9a-zA-Z.@_-]{1,13}$/", $location))) {
            throw new WrongTransactionPayloadData("Wrong location format '{$location}'");
        }

        $this->location = $location;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    /**
     * @throws WrongTransactionPayloadData
     */
    public function setCountry(?string $country): self
    {
        if (empty(preg_match("/^[A-Z]{2}$/", $country))) {
            throw new WrongTransactionPayloadData("Wrong country format '{$country}'");
        }

        $this->country = $country;
        return $this;
    }
}
