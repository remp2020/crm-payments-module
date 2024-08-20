<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Tests\Models\VatRate;

use Crm\ApplicationModule\Models\Database\ActiveRowFactory;
use Crm\ApplicationModule\Tests\CrmTestCase;
use Crm\PaymentsModule\Models\VatRate\VatRateValidator;

class VatRateValidatorTest extends CrmTestCase
{
    private ActiveRowFactory $activeRowFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeRowFactory = $this->inject(ActiveRowFactory::class);
    }

    public function testValidate(): void
    {
        $countryVatRate = $this->activeRowFactory->create([
            'standard' => 1,
            'eperiodical' => 2.2,
            'ebook' => 3.0,
            'reduced' => '[4, 5.1]',
        ]);

        $vatRateValidator = new VatRateValidator();
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 1));
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 2.2));
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 3));
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 4));
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 5.1));
        $this->assertTrue($vatRateValidator->validate($countryVatRate, 0, allowZeroVatRate: true));
        $this->assertFalse($vatRateValidator->validate($countryVatRate, 6));
        $this->assertFalse($vatRateValidator->validate($countryVatRate, 0));
    }
}
