<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NullablePaymentCardsExpiration extends AbstractMigration
{
    public function up()
    {
        $this->table('payment_cards')
            ->changeColumn('expiration', 'datetime', ['null' => true])
            ->update();
    }

    public function down()
    {
        $this->table('payment_cards')
            ->changeColumn('expiration', 'datetime', ['null' => false])
            ->update();
    }
}
