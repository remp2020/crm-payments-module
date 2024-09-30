<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Nette\Database\Explorer;
use Nette\Database\Row;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MigratePaymentMethodsCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private readonly Explorer $database,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:migrate_payment_methods')
            ->setDescription('Migrate payment methods');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $batchSize = 1000;
        $recurrentPaymentCount = (int) $this->database->query("SELECT COUNT(*) as count FROM recurrent_payments WHERE payment_method_id IS NULL")->fetchField();

        $progressBar = new ProgressBar($output, max: $recurrentPaymentCount);
        $progressBar->setMessage('Migrating recurrent payments to payment methods');
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);
        $progressBar->setOverwrite(false);

        while (true) {
            $recurrentPaymentsToUpdate = $this->database
                ->query(sprintf('SELECT id FROM recurrent_payments WHERE payment_method_id IS NULL LIMIT %d', $batchSize))
                ->fetchAll();

            $recurrentPaymentIdsToUpdate = array_map(static fn (Row $recurrentPayment) => $recurrentPayment->id, $recurrentPaymentsToUpdate);

            if (count($recurrentPaymentIdsToUpdate) === 0) {
                break;
            }

            // there's an unique index on (`user_id`, `payment_gateway_id`, `external_token`) to get rid of duplicates
            $this->database->query(
                <<<SQL
INSERT IGNORE INTO payment_methods (user_id, payment_gateway_id, external_token, created_at, updated_at)
    SELECT rp.user_id, rp.payment_gateway_id, rp.cid, rp.created_at, rp.created_at FROM recurrent_payments rp
    WHERE rp.id IN (?set);

UPDATE recurrent_payments rp
    INNER JOIN payment_methods pm ON (
        pm.user_id = rp.user_id AND
        pm.payment_gateway_id = rp.payment_gateway_id AND
        pm.external_token = rp.cid
    )

    SET rp.payment_method_id = pm.id
    WHERE rp.id IN (?set) AND rp.payment_method_id IS NULL;
SQL,
                $recurrentPaymentIdsToUpdate,
                $recurrentPaymentIdsToUpdate,
            );

            $progressBar->advance(count($recurrentPaymentIdsToUpdate));
        }

        return Command::SUCCESS;
    }
}
