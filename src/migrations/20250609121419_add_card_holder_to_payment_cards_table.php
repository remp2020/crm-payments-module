<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCardHolderToPaymentCardsTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('payment_cards')
            ->addColumn('card_holder_name', 'string', ['null' => true, 'after' => 'masked_card_number'])
            ->update();
    }

    public function down(): void
    {
        $this->table('payment_cards')
            ->removeColumn('card_holder_name')
            ->update();
    }
}
