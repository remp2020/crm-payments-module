<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddReducedIntoVatRatesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('vat_rates')
            ->addColumn('reduced', 'json', [
                'null' => false,
                'default' => "[]",
                'after' => 'standard',
                'comment' => 'Reduced VAT rates (eg. print).',
            ])
            ->update();
    }
}
