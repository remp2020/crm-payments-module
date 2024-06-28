<?php

namespace Crm\PaymentsModule\Models\GeoIp;

use Crm\UsersModule\Repositories\CountriesRepository;

final class StaticGeoReader implements GeoIpInterface
{
    private array $map = [];
    private string $default;

    public function __construct(private CountriesRepository $countriesRepository)
    {
    }

    public function setIpCountry(string $ip, string $countryCode)
    {
        $this->map[$ip] = $countryCode;
    }

    public function useDefaultCountryFallback()
    {
        $this->default = $this->countriesRepository->defaultCountry()->iso_code;
    }

    public function countryCode(string $ip): ?string
    {
        if (isset($this->map[$ip])) {
            return $this->map[$ip];
        }

        if (isset($this->default)) {
            return $this->default;
        }

        throw new GeoIpException("Unable to resolve country for {$ip}.");
    }
}
