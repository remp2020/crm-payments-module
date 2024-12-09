<?php declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Database\Table\ActiveRow;

class ChangePaymentCountryDataProvider implements ChangePaymentCountryDataProviderInterface
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
    ) {
    }

    public function changePaymentCountry(ActiveRow $payment, CountryResolution $countryResolution): void
    {
        // Update of VAT rates are handled within 'update' (via OneStopShop, ...)
        $this->paymentsRepository->update($payment, [
            'payment_country_id' => $countryResolution->country->id,
            'payment_country_resolution_reason' => $countryResolution->getReasonValue(),
        ]);
    }
}
