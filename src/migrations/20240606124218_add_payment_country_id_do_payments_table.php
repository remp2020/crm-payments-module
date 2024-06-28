<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPaymentCountryIdDoPaymentsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('payments')
            ->addColumn('payment_country_id', 'integer', ['null' => true, 'after' => 'address_id'])
            ->addForeignKey('payment_country_id', 'countries')
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }

    public function down(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('payments')
            ->dropForeignKey('payment_country_id')
            ->removeColumn('payment_country_id')
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }
}
