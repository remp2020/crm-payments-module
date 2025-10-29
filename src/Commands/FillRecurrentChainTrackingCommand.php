<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tracy\Debugger;

class FillRecurrentChainTrackingCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('payments:fill_recurrent_chain_tracking')
            ->setDescription('Fill chain_id and cycle columns in recurrent_payments table')
            ->addOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of records to update per batch (default: 1000)',
                1000,
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        Debugger::timer();
        $batchSize = (int) $input->getOption('batch-size');

        $this->line("  * Batch size: <comment>{$batchSize}</comment>");

        // Step 1: Setup session
        $this->line('  * Setting up MySQL session...');
        try {
            $this->recurrentPaymentsRepository->getDatabase()->query("SET SESSION cte_max_recursion_depth = 10000;");
            $this->line('    ✓ Session configured');
        } catch (Exception $e) {
            $this->error('Failed to setup session: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Step 2: Build chain hierarchy
        $this->line('  * Building chain hierarchy with recursive CTE...');
        try {
            // Drop temp table if exists
            $this->recurrentPaymentsRepository->getDatabase()->query("DROP TEMPORARY TABLE IF EXISTS tmp_chain_data");

            // Create temporary table
            $this->recurrentPaymentsRepository->getDatabase()->query("
                CREATE TEMPORARY TABLE tmp_chain_data (
                    recurrent_payment_id INT NOT NULL,
                    chain_id VARCHAR(12) NOT NULL,
                    cycle INT NOT NULL,
                    PRIMARY KEY (recurrent_payment_id)
                )
            ");

            // Execute recursive CTE to populate temp table
            $this->recurrentPaymentsRepository->getDatabase()->query("
                INSERT INTO tmp_chain_data
                WITH RECURSIVE chain_traverse AS (
                    -- Find root payments (no parent pointing to them)
                    SELECT
                        rp.id as recurrent_payment_id,
                        rp.parent_payment_id,
                        rp.payment_id,
                        rp.state,
                        LOWER(LEFT(MD5(CONCAT('chain_', rp.id)), 12)) as chain_id,
                        1 as cycle
                    FROM recurrent_payments rp
                    LEFT JOIN recurrent_payments rp2 ON rp2.payment_id = rp.parent_payment_id
                    WHERE rp2.payment_id IS NULL

                    UNION ALL

                    -- Traverse payment chains
                    SELECT
                        rp_next.id as recurrent_payment_id,
                        rp_next.parent_payment_id,
                        rp_next.payment_id,
                        rp_next.state,
                        ct.chain_id,
                        CASE
                            WHEN ct.state IN ('charged', 'active', 'pending') THEN ct.cycle + 1
                            ELSE ct.cycle
                        END as cycle
                    FROM recurrent_payments rp_next
                    INNER JOIN chain_traverse ct ON rp_next.parent_payment_id = ct.payment_id
                )
                SELECT recurrent_payment_id, chain_id, cycle
                FROM (
                    SELECT recurrent_payment_id, chain_id, cycle,
                           ROW_NUMBER() OVER (PARTITION BY recurrent_payment_id ORDER BY cycle) as rn
                    FROM chain_traverse
                ) t
                WHERE rn = 1
            ");

            $count = $this->recurrentPaymentsRepository->getDatabase()->query("SELECT COUNT(*) FROM tmp_chain_data")->fetchField();
            $this->line("    ✓ Chain data built for <info>{$count}</info> recurrent payments");
        } catch (Exception $e) {
            $this->error('Failed to build chain hierarchy: ' . $e->getMessage());
            $this->cleanup();
            return Command::FAILURE;
        }

        // Step 3: Update in batches
        $this->line('  * Updating recurrent_payments in batches...');
        try {
            // Get total count for progress bar
            $totalCount = $this->recurrentPaymentsRepository->getDatabase()->query("SELECT COUNT(*) FROM recurrent_payments")->fetchField();

            // Create progress bar
            $progressBar = new ProgressBar($this->output, $totalCount);
            $progressBar->setFormat('    %current%/%max% [%bar%] %percent:3s%%');
            $progressBar->start();

            $lastId = 0;
            $totalUpdated = 0;

            while (true) {
                // Fetch next batch of IDs
                $ids = $this->recurrentPaymentsRepository->getDatabase()->query("
                    SELECT id FROM recurrent_payments
                    WHERE id > ?
                    ORDER BY id
                    LIMIT ?
                ", $lastId, $batchSize)->fetchAll();

                if (empty($ids)) {
                    break;  // No more records
                }

                $idValues = array_column($ids, 'id');

                // Update this batch
                $this->recurrentPaymentsRepository->getDatabase()->query("
                    UPDATE recurrent_payments rp
                    LEFT JOIN tmp_chain_data tcd ON rp.id = tcd.recurrent_payment_id
                    SET
                        rp.chain_id = tcd.chain_id,
                        rp.cycle = tcd.cycle
                    WHERE rp.id IN (?)
                ", $idValues);

                $lastId = max($idValues);
                $totalUpdated += count($idValues);

                // Advance progress bar
                $progressBar->advance(count($idValues));
            }

            $progressBar->finish();
            $this->line('');
            $this->line("    ✓ Updated <info>{$totalUpdated}</info> recurrent payments");
        } catch (Exception $e) {
            $this->line('');
            $this->error('Failed during batch update: ' . $e->getMessage());
            $this->cleanup();
            return Command::FAILURE;
        }

        // Step 4: Cleanup
        $this->line('  * Cleaning up...');
        $this->cleanup();

        // Step 5: Report unpopulated records
        $this->line('  * Checking for unpopulated records...');
        $unpopulated = $this->recurrentPaymentsRepository->getDatabase()->query("
            SELECT id, parent_payment_id, payment_id, state, created_at
            FROM recurrent_payments
            WHERE chain_id IS NULL OR cycle IS NULL
            ORDER BY id
        ")->fetchAll();

        if (!empty($unpopulated)) {
            $this->line('');
            $this->warn(
                '! Warning: Found ' . count($unpopulated) . ' recurrent payments with missing chain_id/cycle:',
            );
            $this->line('');
            foreach ($unpopulated as $record) {
                $this->line(sprintf(
                    '    ID: %d | parent_payment_id: %s | payment_id: %s | state: %s | created: %s',
                    $record->id,
                    $record->parent_payment_id ?? 'NULL',
                    $record->payment_id ?? 'NULL',
                    $record->state,
                    $record->created_at,
                ));
            }
            $this->line('');
        } else {
            $this->line('    ✓ All recurrent payments have chain_id and cycle set');
        }

        $duration = Debugger::timer();

        $this->line('');
        $this->info('All done. Took ' . round($duration, 2) . ' sec.');
        $this->line('');

        return Command::SUCCESS;
    }

    private function cleanup(): void
    {
        try {
            $this->recurrentPaymentsRepository->getDatabase()->query("DROP TEMPORARY TABLE IF EXISTS tmp_chain_data");
        } catch (Exception) {
            // Ignore - temp table may not exist
        }

        $this->line('    ✓ Cleanup completed');
    }
}
