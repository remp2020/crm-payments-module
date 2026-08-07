<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusIndexForRecurrentPaymentsTable extends AbstractMigration
{
    public function change()
    {
        $this->table('recurrent_payments')
            ->addIndex('status')
            ->update();
    }
}
