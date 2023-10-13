<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIndexToPaymentMetaValueColumn extends AbstractMigration
{
    public function change(): void
    {
        $this->table('payment_meta')
            ->addIndex('value')
            ->update();
    }
}
