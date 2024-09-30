<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentMethodRelationToRecurrentPayments extends AbstractMigration
{
    public function up(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('recurrent_payments')
            ->addColumn('payment_method_id', 'integer', ['null' => true, 'after' => 'cid'])
            ->addForeignKey('payment_method_id', 'payment_methods')
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }

    public function down(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('recurrent_payments')
            ->dropForeignKey('payment_method_id')
            ->removeColumn('payment_method_id')
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }
}
