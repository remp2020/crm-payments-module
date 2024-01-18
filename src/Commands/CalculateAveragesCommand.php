<?php

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UserStatsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use Nette\Database\Explorer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Calculates average and total amounts of money spent and stores it in user's stats data.
 *
 * This stats data is mainly used by admin widget TotalUserPayments.
 */
class CalculateAveragesCommand extends Command
{
    private const PAYMENT_STATUSES = [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID];

    use DecoratedCommandTrait;

    private Explorer $database;

    private UserStatsRepository $userStatsRepository;

    private UsersRepository $usersRepository;

    private UserMetaRepository $userMetaRepository;

    public function __construct(
        Explorer $database,
        UserStatsRepository $userStatsRepository,
        UsersRepository $usersRepository,
        UserMetaRepository $userMetaRepository
    ) {
        parent::__construct();
        $this->database = $database;
        $this->userStatsRepository = $userStatsRepository;
        $this->usersRepository = $usersRepository;
        $this->userMetaRepository = $userMetaRepository;
    }

    protected function configure()
    {
        $this->setName('payments:calculate_averages')
            ->setDescription('Calculate payment-related averages')
            ->addOption(
                'delete',
                null,
                InputOption::VALUE_NONE,
                "Force deleting existing data in 'user_stats' table and 'user_meta' table (where data was originally stored)"
            )
            ->addOption(
                'user_id',
                null,
                InputOption::VALUE_REQUIRED,
                "Compute average values for given user only."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $keys = ['subscription_payments', 'subscription_payments_amount', 'avg_month_payment'];

        if ($input->getOption('delete')) {
            $this->line("  * deleting old values from 'user_stats' and 'user_meta' tables.");

            $this->userStatsRepository->getTable()
                ->where('key IN (?)', $keys)
                ->delete();

            $this->userMetaRepository->getTable()
                ->where('key IN (?)', $keys)
                ->delete();
        }

        $userId = $input->getOption('user_id');

        foreach ($keys as $key) {
            $this->line("  * filling up 0s for '<info>{$key}</info>' stat");

            if ($userId) {
                $this->database->query(<<<SQL
                INSERT IGNORE INTO `user_stats` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
                VALUES (?, ?, 0, NOW(), NOW())
SQL, $userId, $key);
            } else {
                $this->database->query(<<<SQL
                -- fill empty values for new users
                INSERT IGNORE INTO `user_stats` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
                SELECT `id`, ?, 0, NOW(), NOW()
                FROM `users`;
SQL, $key);
            }
        }

        if ($userId) {
            $interval = [$userId, $userId];
            $this->computeUserSubscriptionPaymentCounts(...$interval);
            $this->computeUserSubscriptionPaymentAmounts(...$interval);
            $this->computeUserAvgPaymentAmount(...$interval);
        } else {
            foreach ($this->userIdIntervals() as $interval) {
                $this->computeUserSubscriptionPaymentCounts(...$interval);
                $this->computeUserSubscriptionPaymentAmounts(...$interval);
                $this->computeUserAvgPaymentAmount(...$interval);
            }
        }

        return Command::SUCCESS;
    }

    private function userIdIntervals(): array
    {
        $windowSize = 100000;

        $minId = $this->usersRepository->getTable()->min('id');
        $maxId = $this->usersRepository->getTable()->max('id');

        $intervals = [];
        $i = $minId;
        while ($i <= $maxId) {
            $nextI = $i + $windowSize;
            $intervals[] = [$i, $nextI - 1];
            $i = $nextI;
        }
        return $intervals;
    }

    private function computeUserSubscriptionPaymentCounts($minUserId, $maxUserId)
    {
        $this->line("  * computing '<info>subscription_payments</info>' for user IDs between [<info>{$minUserId}</info>, <info>{$maxUserId}</info>]");

        $subscriptionTypeItem = SubscriptionTypePaymentItem::TYPE;

        $values = $this->database->query(<<<SQL
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COUNT(DISTINCT(`payments`.`id`)) AS `subscription_payments`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` IN (?)
                WHERE `payment_items`.`type` IN (?) AND `payments`.`user_id` BETWEEN ? AND ?
                GROUP BY `payments`.`user_id`
SQL, self::PAYMENT_STATUSES, $subscriptionTypeItem, $minUserId, $maxUserId)
            ->fetchPairs('user_id', 'subscription_payments');

        $this->userStatsRepository->upsertUsersValues('subscription_payments', $values);
    }

    private function computeUserSubscriptionPaymentAmounts($minUserId, $maxUserId)
    {
        $this->line("  * computing '<info>subscription_payments_amount</info>' for user IDs between [<info>{$minUserId}</info>, <info>{$maxUserId}</info>]");

        $subscriptionTypeItem = SubscriptionTypePaymentItem::TYPE;

        $values = $this->database->query(<<<SQL
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COALESCE(SUM(`payment_items`.`amount` * `payment_items`.`count`), 0) AS `subscription_payments_amount`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` IN (?)
                WHERE `payment_items`.`type` IN (?) and `payments`.`user_id` BETWEEN ? AND ?
                GROUP BY `payments`.`user_id`
SQL, self::PAYMENT_STATUSES, $subscriptionTypeItem, $minUserId, $maxUserId)
            ->fetchPairs('user_id', 'subscription_payments_amount');

        $this->userStatsRepository->upsertUsersValues('subscription_payments_amount', $values);
    }

    public function computeUserAvgPaymentAmount($minUserId, $maxUserId)
    {
        $this->line("  * computing '<info>avg_month_payment</info>' for user IDs between [<info>{$minUserId}</info>, <info>{$maxUserId}</info>]");

        $values = $this->database->query(<<<SQL
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COALESCE(AVG((`payments`.`amount` / `subscription_types`.`length`) * 31), 0) AS `avg_month_payment_amount`
                FROM `payments`
                INNER JOIN `subscription_types`
                   ON `subscription_types`.`id` = `payments`.`subscription_type_id`
                   AND `subscription_types`.`length` > 0
                WHERE
                    `payments`.`status` IN (?) AND `payments`.`user_id` BETWEEN ? AND ?
                GROUP BY `payments`.`user_id`
SQL, self::PAYMENT_STATUSES, $minUserId, $maxUserId)
            ->fetchPairs('user_id', 'avg_month_payment_amount');

        $this->userStatsRepository->upsertUsersValues('avg_month_payment', $values);
    }
}
