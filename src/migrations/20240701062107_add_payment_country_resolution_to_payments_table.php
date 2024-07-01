<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPaymentCountryResolutionToPaymentsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payments')
            ->addColumn('payment_country_resolution_reason', 'string', ['null' => true, 'after' => 'payment_country_id'])
            ->update();
    }
}
