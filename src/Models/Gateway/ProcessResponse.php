<?php

namespace Crm\PaymentsModule\Gateways;

class ProcessResponse
{
    private string $type;

    private $data;

    /**
     * @param string       $type
     * @param string|array $data
     */
    public function __construct(string $type, $data)
    {
        $this->type = $type;
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string|array
     */
    public function getData()
    {
        return $this->data;
    }
}
