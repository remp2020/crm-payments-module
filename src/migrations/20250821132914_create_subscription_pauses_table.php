<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSubscriptionPausesTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('subscription_pauses')
            ->addColumn('subscription_id', 'integer', ['null' => false])
            ->addColumn('pause_at', 'datetime', ['null' => false])
            ->addColumn('resume_at', 'datetime', ['null' => false])
            ->addColumn('changes', 'json', ['null' => false])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addForeignKey('subscription_id', 'subscriptions', 'id')
            ->create();
    }
}
