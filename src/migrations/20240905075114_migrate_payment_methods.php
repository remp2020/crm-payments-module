<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Symfony\Component\Console\Helper\ProgressBar;
use Tracy\Debugger;

final class MigratePaymentMethods extends AbstractMigration
{
    private const DUMMY_CID_TOKEN = '__no_token';

    public function up(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->fixNoCIDs();

        $this->migrateCidToPaymentMethods();

        $this->execute('SET foreign_key_checks = 1');
    }

    public function down(): void
    {
        $this->execute('SET foreign_key_checks = 0');

        $this->query("UPDATE recurrent_payments SET payment_method_id = NULL");

        $this->query(<<<SQL
UPDATE recurrent_payments
SET cid = NULL
WHERE cid = :no_token;
SQL, ['no_token' => self::DUMMY_CID_TOKEN]);

        $this->query("TRUNCATE TABLE `payment_methods`");

        $this->execute('SET foreign_key_checks = 1');
    }

    private function migrateCidToPaymentMethods(): void
    {
        $batchSize = 1000;
        $recurrentPaymentCount = $this->query("SELECT COUNT(*) as count FROM recurrent_payments")->fetch();

        $progressBar = new ProgressBar($this->output, max: $recurrentPaymentCount['count']);
        $progressBar->setMessage('Migrating recurrent payments to payment methods');
        $progressBar->setFormat(ProgressBar::FORMAT_VERY_VERBOSE);
        $progressBar->setOverwrite(false);

        while (true) {
            $recurrentPaymentsToUpdate = $this
                ->query(sprintf('SELECT id FROM recurrent_payments WHERE payment_method_id IS NULL LIMIT %d', $batchSize))
                ->fetchAll(PDO::FETCH_ASSOC);

            $recurrentPaymentIdsToUpdate = array_column($recurrentPaymentsToUpdate, 'id');

            // cast to int
            $recurrentPaymentIdsToUpdate = array_map(
                fn ($id) => (int) $id,
                $recurrentPaymentIdsToUpdate,
            );

            if (count($recurrentPaymentIdsToUpdate) === 0) {
                break;
            }

            $inValuePlaceholders = rtrim(str_repeat('?,', count($recurrentPaymentIdsToUpdate)), ',');

            // there's an unique index on (`user_id`, `payment_gateway_id`, `external_token`) to get rid of duplicates
            $this->query(<<<SQL
INSERT IGNORE INTO payment_methods (user_id, payment_gateway_id, external_token, created_at, updated_at)
    SELECT rp.user_id, rp.payment_gateway_id, rp.cid, rp.created_at, rp.created_at FROM recurrent_payments rp
    WHERE rp.id IN ($inValuePlaceholders);

UPDATE recurrent_payments rp
    INNER JOIN payment_methods pm ON (
        pm.user_id = rp.user_id AND
        pm.payment_gateway_id = rp.payment_gateway_id AND
        pm.external_token = rp.cid
    )

    SET rp.payment_method_id = pm.id
    WHERE rp.id IN ($inValuePlaceholders) AND rp.payment_method_id IS NULL;
SQL,
                [...$recurrentPaymentIdsToUpdate, ...$recurrentPaymentIdsToUpdate]
            );

            /*
             * Commit transaction to avoid locking the table (`recurrent_payments`) for too long.
             * https://github.com/cakephp/phinx/issues/688
             */
            $this->getAdapter()->commitTransaction();
            $progressBar->advance(count($recurrentPaymentIdsToUpdate));

            $this->getAdapter()->beginTransaction();
        }

        $progressBar->finish();
        $this->output->writeln('');
        $this->output->writeln('DONE');
    }

    private function fixNoCIDs(): void
    {
        $a = $this->query(<<<SQL
UPDATE recurrent_payments
SET cid = :no_token
WHERE cid IS NULL OR cid = '';
SQL, ['no_token' => self::DUMMY_CID_TOKEN]);

        $affectedRows = $a->rowCount();
        if ($affectedRows > 0) {
            $logMessage = sprintf(
                "During '20240905075114_migrate_payment_methods' migration, %d recurring payments with NULL or empty 'cid's were fixed and replaced by '%s'.",
                $affectedRows,
                self::DUMMY_CID_TOKEN,
            );

            Debugger::log($logMessage);
            $this->output->writeln($logMessage);
        }
    }
}
