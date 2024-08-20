<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\VatRate;

use Nette\Database\Table\ActiveRow;
use Nette\Utils\Json;

class VatRateValidator
{
    public function validate(?ActiveRow $countryVatRate, float $vatRate, bool $allowZeroVatRate = false): bool
    {
        // if no country VAT rate is provided, 0% VAT is OK
        if (!$countryVatRate) {
            $allowedVatRates = [0];
        } else {
            $allowedVatRates = $this->getAllowedVatRates($countryVatRate, $allowZeroVatRate);
        }

        return in_array($vatRate, $allowedVatRates, strict: true);
    }

    /**
     * @return float[]
     */
    private function getAllowedVatRates(ActiveRow $countryVatRate, bool $allowZeroVatRate): array
    {
        $reducedVatRates = Json::decode($countryVatRate->reduced, true);

        $allowedVatRates = [
            $countryVatRate->standard,
            $countryVatRate->eperiodical,
            $countryVatRate->ebook,
            ...$reducedVatRates,
        ];

        if ($allowZeroVatRate) {
            $allowedVatRates[] = 0;
        }

        return array_map('floatval', $allowedVatRates);
    }
}
