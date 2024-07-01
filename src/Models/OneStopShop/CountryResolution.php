<?php

namespace Crm\PaymentsModule\Models\OneStopShop;

final class CountryResolution
{
    public function __construct(
        public readonly string $countryCode,
        public readonly CountryResolutionType|string $reason,
    ) {
    }

    public function getReasonValue(): string
    {
        if ($this->reason instanceof CountryResolutionType) {
            return $this->reason->value;
        }

        return $this->reason;
    }
}
