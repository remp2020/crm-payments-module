<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\OneStopShop;

trait OneStopShopAddressCheckTrait
{

    /**
     * @throws OneStopShopCountryConflictException
     */
    public function checkAddresses(array $addresses, array $allowedCountryCodes, string $allowedCountriesDescription = ''): void
    {
        if (count($allowedCountryCodes) === 0) {
            throw new \RuntimeException("No allowed countries codes, probably some should be allowed");
        }

        if (count($allowedCountryCodes) === 1) {
            $allowedCountriesString = "allowed {$allowedCountriesDescription} country [{$allowedCountryCodes[0]}]";
        } else {
            $list = implode(', ', $allowedCountryCodes);
            $allowedCountriesString = "allowed {$allowedCountriesDescription} countries [{$list}]";
        }

        foreach ($addresses as $addressName => $addressCountryIsoCode) {
            if ($addressCountryIsoCode && !in_array($addressCountryIsoCode, $allowedCountryCodes, true)) {
                throw new OneStopShopCountryConflictException("Conflicting address [{$addressName}]" .
                    " country code [{$addressCountryIsoCode}], outside of {$allowedCountriesString}");
            }
        }
    }
}
