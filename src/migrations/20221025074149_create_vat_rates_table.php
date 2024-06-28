<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateVatRatesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('vat_rates')
            ->addColumn('country_id', 'integer', ['null' => false])
            ->addColumn('standard', 'float', ['null' => false])
            ->addColumn('eperiodical', 'float', [
                'null' => true,
                'comment' => 'VAT rate for online periodical publications.',
            ])
            ->addColumn('ebook', 'float', ['null' => true])
            ->addColumn('valid_from', 'datetime', ['null' => true])
            ->addColumn('valid_to', 'datetime', [
                'null' => true,
                'comment' => 'Set only if new rates for country were added.',
            ])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('country_id', 'countries', 'id')
            ->addIndex('valid_from')
            ->addIndex('valid_to')
            ->create();
    }
}
