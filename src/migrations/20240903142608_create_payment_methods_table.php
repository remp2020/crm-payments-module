<?php declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreatePaymentMethodsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_methods')

            ->addColumn('user_id', 'integer', ['null' => false])
            ->addForeignKey('user_id', 'users', 'id')

            ->addColumn('payment_gateway_id', 'integer', ['null' => false])
            ->addForeignKey('payment_gateway_id', 'payment_gateways', 'id')

            ->addColumn('external_token', 'string', ['null' => false, 'limit' => 255])
            ->addIndex(['user_id', 'payment_gateway_id', 'external_token'], ['unique' => true])

            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])

            ->create();
    }
}
