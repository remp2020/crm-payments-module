<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddCreatedAtAndUpdatedAtColumnsToPaymentMeta extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<SQL
            ALTER TABLE payment_meta
                ADD COLUMN created_at DATETIME NOT NULL DEFAULT NOW(),
                ADD COLUMN updated_at DATETIME NOT NULL DEFAULT NOW(),
                ALGORITHM=INSTANT
            SQL
        );

        $this->execute(<<<SQL
            ALTER TABLE payment_meta
                MODIFY COLUMN created_at DATETIME NOT NULL,
                MODIFY COLUMN updated_at DATETIME NOT NULL,
                ALGORITHM=INSTANT
            SQL
        );
    }

    public function down(): void
    {
        $this->execute(<<<SQL
            ALTER TABLE payment_meta
                DROP COLUMN created_at,
                DROP COLUMN updated_at,
                ALGORITHM=INSTANT
            SQL
        );
    }
}
