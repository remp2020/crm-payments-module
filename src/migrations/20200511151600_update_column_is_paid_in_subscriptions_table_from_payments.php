<?php

use Phinx\Migration\AbstractMigration;

class UpdateColumnIsPaidInSubscriptionsTableFromPayments extends AbstractMigration
{
    public function change()
    {
        $this->query("
            UPDATE subscriptions s
                LEFT JOIN payments p ON s.id = p.subscription_id
            SET s.is_paid = IF (p.id IS NULL, 0, 1)
            WHERE s.type IN ('regular', 'special') AND s.is_paid IS NULL
        ");
    }
}
