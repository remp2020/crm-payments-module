<?php

namespace Crm\PaymentsModule\Models\GeoIp;

interface GeoIpInterface
{
    /**
     * @throws GeoIpException
     */
    public function countryCode(string $ip): ?string;
}
