<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigrateRenewalPaymentsToSubscriptionsMeta extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
            INSERT INTO subscriptions_meta (subscription_id, `key`, `value`, created_at, updated_at)
            SELECT
                SUBSTRING_INDEX(value, ':', -1) AS subscription_id,
                'renewal_payment_id' AS `key`,
                payment_id AS `value`,
                NOW(),
                NOW()
            FROM
                payment_meta
            WHERE
                `key` = 'context'
              AND value LIKE 'renewal:%';
SQL;

        $this->execute($sql);
    }

    public function down()
    {
        $this->output->writeln('This is data migration. Down migration is not available.');
    }
}
