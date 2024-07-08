<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\OneStopShop;

final class OneStopShopFrontendData
{
    public readonly ?string $prefilledCountryName;

    /**
     * @param bool        $enabled
     * @param array       $countries associative array containing country_iso_code => country_name pairs
     * @param string|null $prefilledCountryCode
     * @param string|null $prefilledCountryReason
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $countries,
        public readonly ?string $prefilledCountryCode,
        public readonly ?string $prefilledCountryReason,
    ) {
        $this->prefilledCountryName = $countries[$prefilledCountryCode] ?? null;
    }
}
