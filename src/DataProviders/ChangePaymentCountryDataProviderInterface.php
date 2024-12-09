<?php declare(strict_types=1);

namespace Crm\PaymentsModule\DataProviders;

use Crm\ApplicationModule\Models\DataProvider\DataProviderInterface;
use Crm\PaymentsModule\Models\OneStopShop\CountryResolution;
use Nette\Database\Table\ActiveRow;

interface ChangePaymentCountryDataProviderInterface extends DataProviderInterface
{
    public function changePaymentCountry(ActiveRow $payment, CountryResolution $countryResolution): void;
}
