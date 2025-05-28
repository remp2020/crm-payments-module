<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PaymentCardsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_cards')

            ->addColumn('payment_method_id', 'integer', ['null' => false])
            ->addForeignKey('payment_method_id', 'payment_methods', 'id')

            ->addColumn('masked_card_number', 'string', ['null' => true])
            ->addColumn('expiration', 'datetime', ['null' => false])
            ->addColumn('description', 'string', ['null' => true])

            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex('payment_method_id', ['unique' => true])
            ->create();
    }
}
