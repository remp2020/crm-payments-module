<?php

namespace Crm\PaymentsModule\Models\OneStopShop;

use Nette\Database\Table\ActiveRow;

final class CountryResolution
{
    public function __construct(
        public readonly ActiveRow $country,
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
