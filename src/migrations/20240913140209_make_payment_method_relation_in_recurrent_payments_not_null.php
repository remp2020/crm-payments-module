<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MakePaymentMethodRelationInRecurrentPaymentsNotNull extends AbstractMigration
{
    public function up()
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('recurrent_payments')
            ->changeColumn('payment_method_id', 'integer', ['null' => false])
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }

    public function down()
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->table('recurrent_payments')
            ->changeColumn('payment_method_id', 'integer', ['null' => true])
            ->update();

        $this->execute('SET foreign_key_checks = 1');
    }
}
