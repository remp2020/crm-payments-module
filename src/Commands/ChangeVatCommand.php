<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemHelper;
use Crm\PaymentsModule\Repositories\PaymentItemMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentItemsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypeItemsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class ChangeVatCommand extends Command
{
    use DecoratedCommandTrait;

    public function __construct(
        private SubscriptionTypeItemsRepository $subscriptionTypeItemsRepository,
        private SubscriptionTypeItemMetaRepository $subscriptionTypeItemMetaRepository,
        private PaymentItemsRepository $paymentItemsRepository,
        private PaymentItemMetaRepository $paymentItemMetaRepository,
        private ApplicationConfig $applicationConfig,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:change_vat')
            ->setDescription('Changes VAT to selected subscription type items and related payments.')
            ->addOption(
                'original-vat',
                null,
                InputOption::VALUE_REQUIRED,
                "VAT of subscription type items to be changed.",
            )
            ->addOption(
                'target-vat',
                null,
                InputOption::VALUE_REQUIRED,
                "VAT to be applied to selected subscription type items",
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                "Outputs changes. Doesn't change anything. Use with --verbose for full output.",
            )
            ->addOption(
                'exclude-by-name',
                null,
                InputOption::VALUE_REQUIRED,
                "Excludes subscription type items and payment items by name. Regexp not supported. 'NOT LIKE %string%' is used ('book' excludes both 'book' and 'e-book').",
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                "Forces the execution without interactive mode (for cron)",
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $originalVat = $input->getOption('original-vat');
        if ($originalVat === null) {
            $output->writeln('<error>ERR</error>: Option --original-vat is required.');
            return Command::FAILURE;
        }

        $targetVat = $input->getOption('target-vat');
        if ($targetVat === null) {
            $output->writeln('<error>ERR</error>: Option --target-vat is required.');
            return Command::FAILURE;
        }

        $excludeByName = $input->getOption('exclude-by-name');
        $dryRun = (bool) $input->getOption('dry-run');
        $verbose = (bool) $input->getOption('verbose'); // this is already defined by Symfony command
        $force = (bool) $input->getOption('force');
        $currency = $this->applicationConfig->get('currency');

        $subscriptionTypeItems = $this->subscriptionTypeItemsRepository->getTable()
            ->where('vat = ?', $originalVat)
            ->order('subscription_type_id, subscription_type_items.id');
        if ($excludeByName !== null) {
            $subscriptionTypeItems->where('name NOT LIKE ?', '%'.$excludeByName.'%');
        }

        $subscriptionTypeItemsCount = (clone $subscriptionTypeItems)->count('*');

        if ($subscriptionTypeItemsCount) {
            $output->writeln("Listing subscription type items / subscription types to be changed to {$targetVat}% VAT:");
            foreach ($subscriptionTypeItems as $subscriptionTypeItem) {
                $output->writeln("  * <info>{$subscriptionTypeItem->name}</info> ($subscriptionTypeItem->amount {$currency}) / {$subscriptionTypeItem->subscription_type->code}");
            }
        } else {
            $output->writeln("No subscription type item has {$originalVat}% VAT anymore.");
        }

        $paymentItemsQuery = $this->paymentItemsRepository->getTable()
            ->where('vat = ?', $originalVat)
            ->where('type = ?', SubscriptionTypePaymentItem::TYPE)
            ->where('payment.status = ?', PaymentStatusEnum::Form->value)
            ->order('payment_items.id');
        if ($excludeByName !== null) {
            $paymentItemsQuery->where('name NOT LIKE ?', '%'.$excludeByName.'%');
        }

        $paymentItemsCount = (clone $paymentItemsQuery)->count('*');
        $output->writeln("There are <info>{$paymentItemsCount}</info> payment items of unconfirmed payments with {$originalVat}% VAT to be updated.");

        if (!$dryRun && !$force) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "\n********\n<comment>Do you wish to proceed?</comment> This will update <comment>{$subscriptionTypeItemsCount}</comment> subscription types, and <comment>{$paymentItemsCount}</comment> payment items of unpaid payments with {$originalVat}% VAT. (y/N) ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                return Command::SUCCESS;
            }
        }

        // ********************************************************************
        $output->write("Updating subscription type items (in transaction): ");
        if ($verbose) {
            $output->writeln('');
        }

        $this->subscriptionTypeItemsRepository->getTransaction()->start();
        try {
            foreach ($subscriptionTypeItems as $subscriptionTypeItem) {
                $this->subscriptionTypeItemsRepository->update($subscriptionTypeItem, [
                    'vat' => $targetVat,
                ], true);
            }

            if ($dryRun) {
                $this->subscriptionTypeItemsRepository->getTransaction()->rollback();
            } else {
                $this->subscriptionTypeItemsRepository->getTransaction()->commit();
            }
            $output->writeln("OK");
        } catch (\Exception $e) {
            $output->writeln("<error>ERR</error>: {$e->getMessage()} (rolling back)");
            $this->subscriptionTypeItemsRepository->getTransaction()->rollback();
            throw $e;
        }

        // ********************************************************************
        $output->write("Updating payment items (in transaction): ");
        $lastId = 0;
        if ($verbose) {
            $output->writeln('');
        }

        // set limit; we'll be updating in steps
        $paymentItemsQuery->limit(1000);

        $this->paymentItemsRepository->getTransaction()->start();
        try {
            while (true) {
                $paymentItems = (clone $paymentItemsQuery)->where('payment_items.id > ?', $lastId)->fetchAll();
                if (!count($paymentItems)) {
                    break;
                }

                foreach ($paymentItems as $paymentItem) {
                    $lastId = $paymentItem->id;
                    $targetAmountWithoutVat = PaymentItemHelper::getPriceWithoutVAT($paymentItem->amount, $targetVat);

                    if ($verbose) {
                        $output->write("  * Recalculating #{$paymentItem->id} {$paymentItem->amount} {$currency} ($paymentItem->amount_without_vat -> <info>$targetAmountWithoutVat</info>): ");
                    }

                    $this->paymentItemsRepository->update($paymentItem, [
                        'vat' => $targetVat,
                        'amount_without_vat' => $targetAmountWithoutVat,
                    ], true);

                    if ($verbose) {
                        $output->writeln('OK');
                    }
                }
            }

            if ($dryRun) {
                $this->paymentItemsRepository->getTransaction()->rollback();
            } else {
                $this->paymentItemsRepository->getTransaction()->commit();
            }

            if (!$verbose) {
                $output->writeln('OK');
            }
        } catch (\Exception $e) {
            $output->writeln("<error>ERR</error>: {$e->getMessage()} (rolling back)");
            $this->paymentItemsRepository->getTransaction()->rollback();
            throw $e;
        }

        if ($dryRun) {
            $output->writeln('Rolling everything back, this was a <comment>dry run</comment>.');
        }

        return Command::SUCCESS;
    }
}
