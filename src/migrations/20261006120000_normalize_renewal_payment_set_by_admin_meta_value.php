<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NormalizeRenewalPaymentSetByAdminMetaValue extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE subscriptions_meta SET value = '0' WHERE `key` = 'renewal_payment_set_by_admin' AND value = ''");
    }

    public function down(): void
    {
        $this->output->writeln('Down migration is not available.');
    }
}
