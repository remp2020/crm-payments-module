<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Commands;

use Crm\ApplicationModule\Commands\DecoratedCommandTrait;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SegmentModule\Models\SegmentFactory;
use Crm\SubscriptionsModule\Models\PaymentItem\SubscriptionTypePaymentItem;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\UsersModule\Repositories\UserStatsRepository;
use Crm\UsersModule\Repositories\UsersRepository;
use DateInterval;
use DateTime;
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
    use DecoratedCommandTrait;

    private const PAYMENT_STATUSES = [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value];

    private int $subscriptionPeriod = 31;
    private int $minimalSubscriptionLength = 1;
    private ?int $calculatedPeriod = null;
    private ?DateTime $startDate = null;
    private array $excludedSubscriptionTypeIds = [];

    public function __construct(
        private readonly Explorer $database,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly SegmentFactory $segmentFactory,
        private readonly SubscriptionTypesRepository $subscriptionTypesRepository,
        private readonly UserStatsRepository $userStatsRepository,
        private readonly UsersRepository $usersRepository,
        private readonly UserMetaRepository $userMetaRepository,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('payments:calculate_averages')
            ->setDescription('Calculate payment-related averages')
            ->addOption(
                'delete',
                null,
                InputOption::VALUE_NONE,
                "Force deleting existing data in 'user_stats' table and 'user_meta' table (where data was originally stored). If users are provided (--user_id or --segment_code option), only values for provided user(s) are deleted.",
            )
            ->addOption(
                'user_id',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Compute average values for given user(s) only. If this option is used, --segment_code is ignored.",
            )
            ->addOption(
                'segment_code',
                null,
                InputOption::VALUE_REQUIRED,
                "Compute average values for users in provided segment. This option is ignored, if `--user_id` is used.",
            )
            ->addOption(
                'subscription_period',
                null,
                InputOption::VALUE_REQUIRED,
                "Set number of days in month (basic monthly subscription). Useful if monthly subscriptions' length is 28 or 30 days. Default is 31.",
            )
            ->addOption(
                'minimal_subscription_length',
                null,
                InputOption::VALUE_REQUIRED,
                "Set minimal length (in days) of subscription type to include in calculation. Default is 1.",
            )
            ->addOption(
                'calculated_period',
                null,
                InputOption::VALUE_REQUIRED,
                "Set beginning for which should be averages calculated. Use PHP's DateInterval format (eg. P2Y). Default: no beginning; will calculate from all payments.",
            )
            ->addUsage('                                         # no options, all stats are calculated')
            ->addUsage('--user_id=123 --user_id=456              # stats for users with ID #123 and #456 are calculated')
            ->addUsage('--segment_code=active_users              # stats for users in segment `active_users`')
            ->addUsage('--delete                                 # all existing data is removed and recalculated')
            ->addUsage('--delete --user_id=123 --user_id=456     # data of users with ID #123 and #456 is removed and recalculated')
            ->addUsage('--delete --segment_code=active_users     # data of users in segment `active_users` is removed and recalculated')
        ;
    }

    public function setSubscriptionPeriod(int $days): void
    {
        $this->subscriptionPeriod = $days;
    }

    public function setMinimalSubscriptionLength(int $days): void
    {
        $this->minimalSubscriptionLength = $days;
    }

    public function setCalculatedPeriod(int $days): void
    {
        $this->calculatedPeriod = $days;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $keys = ['subscription_payments', 'subscription_payments_amount', 'avg_month_payment'];

        // if set, option subscription_period overrides config and default value
        $subscriptionPeriod = $input->getOption('subscription_period');
        if ($subscriptionPeriod !== null) {
            $this->subscriptionPeriod = (int) $subscriptionPeriod;
        }
        $this->line("  * Subscription period set to: <comment>{$this->subscriptionPeriod}</comment>.");

        // if set, option calculated_period overrides config and default value (no period)
        $calculatedPeriod = $input->getOption('calculated_period');
        if ($calculatedPeriod !== null) {
            $this->calculatedPeriod = (int) $calculatedPeriod;
        }

        if ($this->calculatedPeriod !== null) {
            $interval = new DateInterval("P{$this->calculatedPeriod}D");
            $this->startDate = (new DateTime())->sub($interval);
        } else {
            $firstPaidAt = $this->paymentsRepository->getTable()->where(['paid_at IS NOT NULL'])->order('paid_at ASC')->limit(1)->fetch();
            $this->startDate = $firstPaidAt?->paid_at;
        }

        if ($this->startDate === null) {
            $this->error('Unable to find paid payments. Nothing done.');
            return Command::FAILURE;
        }
        $this->line('  * Including all subscription payments paid after: <comment>' . $this->startDate->format(DATE_RFC3339) . '</comment>.');

        // if set, option minimal_subscription_length overrides config and default value
        $minimalSubscriptionLength = $input->getOption('minimal_subscription_length');
        if ($minimalSubscriptionLength !== null) {
            $this->minimalSubscriptionLength = (int) $minimalSubscriptionLength;
        }
        $this->line("  * Minimal subscription length set to: <comment>{$this->minimalSubscriptionLength}</comment>.");

        $output->write('  * Calculating averages for ');
        $userIDs = $input->getOption('user_id');
        $segmentCode = $input->getOption('segment_code');

        // check user IDs (--user_id has priority over --segment_code)
        if (!empty($userIDs)) {
            $this->line('provided user IDs: ' . implode(', ', $userIDs));
        } elseif ($segmentCode !== null) {
            $segment = $this->segmentFactory->buildSegment($segmentCode);
            $segmentCount = $segment->totalCount();
            $this->line("provided segment: [<comment>{$segmentCode}</comment>] with {$segmentCount} users.");
            $userIDs = $segment->getIds();
        }
        if (empty($userIDs)) {
            $this->line("all users.");
        }

        // delete existing stats (if --delete is used)
        if ($input->getOption('delete')) {
            $this->line("  * deleting old values from 'user_stats' and 'user_meta' tables.");

            $userStats = $this->userStatsRepository->getTable()
                ->where('key IN (?)', $keys);
            if (!empty($userIDs)) {
                $userStats->where('user_id IN (?)', $userIDs);
            }
            $userStats->delete();

            $userMeta = $this->userMetaRepository->getTable()
                ->where('key IN (?)', $keys);
            if (!empty($userIDs)) {
                $userMeta->where('user_id IN (?)', $userIDs);
            }
            $userMeta->delete();
        }

        foreach ($keys as $key) {
            $this->line("  * filling up 0s for '<info>{$key}</info>' stat");

            if (!empty($userIDs)) {
                foreach ($userIDs as $userID) {
                    $this->database->query(<<<SQL
                        INSERT IGNORE INTO `user_stats` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
                        VALUES (?, ?, 0, NOW(), NOW())
                    SQL, $userID, $key);
                }
            } else {
                $this->database->query(<<<SQL
                    -- fill empty values for new users
                    INSERT IGNORE INTO `user_stats` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
                    SELECT users.id, ?, 0, NOW(), NOW()
                    FROM `users`
                    LEFT JOIN user_stats ON user_id = users.id AND `key` = ?
                    WHERE user_stats.id IS NULL;
                SQL, $key, $key);
            }
        }

        // get excluded subscription types (under minimal subscription length
        // or subscription types without subscription (eg. crowdfunding)
        $this->excludedSubscriptionTypeIds = $this->subscriptionTypesRepository->getTable()
            ->where(['length < ?' => $this->minimalSubscriptionLength])
            ->whereOr(['no_subscription' => 1]) // subscription types without subscription shouldn't be in this calculations
            ->fetchPairs('id', 'id');

        if (!empty($userIDs)) {
            $this->computeUserSubscriptionPaymentCounts(userIDs: $userIDs);
            $this->computeUserSubscriptionPaymentAmounts(userIDs: $userIDs);
            $this->computeUserAvgPaymentAmount(userIDs: $userIDs);
        } else {
            foreach ($this->userIdIntervals() as $interval) {
                $this->computeUserSubscriptionPaymentCounts(interval: $interval);
                $this->computeUserSubscriptionPaymentAmounts(interval: $interval);
                $this->computeUserAvgPaymentAmount(interval: $interval);
            }
        }

        $this->line('**** ' . self::getCommandName() . ' (end date: ' . (new DateTime())->format(DATE_RFC3339) . ') ****', 'info');
        $this->line('All done.');

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

    private function computeUserSubscriptionPaymentCounts(array $userIDs = null, array $interval = null)
    {
        $paymentPaidAt = $this->startDate;
        $subscriptionTypeItem = SubscriptionTypePaymentItem::TYPE;

        $paymentsQuery = $this->paymentsRepository->getTable()
            ->select(implode(',', [
                'payments.user_id AS user_id',
                'COUNT(DISTINCT(payments.id)) AS subscription_payments',
            ]))
            ->joinWhere(
                tableChain: ':payment_items',
                condition: "payments.id = :payment_items.payment_id AND :payment_items.type IN (?)",
                params: $subscriptionTypeItem,
            )
            ->where([
                'user.deleted_at IS NULL',
                'payments.status IN (?)' => self::PAYMENT_STATUSES,
                'payments.paid_at > ?' => $paymentPaidAt,
                ':payment_items.id IS NOT NULL',
            ])
            ->group('payments.user_id');

        if (isset($userIDs)) {
            $this->line("  * computing '<info>subscription_payments</info>' for provided user IDs");
            $paymentsQuery->where(['payments.user_id IN (?)' => $userIDs]);
        } elseif (isset($interval)) {
            $this->line("  * computing '<info>subscription_payments</info>' for user IDs between [<info>{$interval[0]}</info>, <info>{$interval[1]}</info>]");
            $paymentsQuery->where(['payments.user_id BETWEEN ? AND ?' => $interval]);
        } else {
            throw new \RuntimeException('Either array of user IDs (e.g. [1,2,3,4,...]) or interval of user IDs (e.g. [1,1000]) need to be provided');
        }

        if (!empty($this->excludedSubscriptionTypeIds)) {
            $paymentsQuery->where(['payments.subscription_type_id NOT IN (?)' => $this->excludedSubscriptionTypeIds,]);
        }

        $values = $paymentsQuery->fetchPairs('user_id', 'subscription_payments');

        $this->userStatsRepository->upsertUsersValues('subscription_payments', $values);
    }

    private function computeUserSubscriptionPaymentAmounts(array $userIDs = null, array $interval = null)
    {
        $paymentPaidAt = $this->startDate;
        $subscriptionTypeItem = SubscriptionTypePaymentItem::TYPE;

        $paymentsQuery = $this->paymentsRepository->getTable()
            ->select(implode(',', [
                'payments.user_id AS user_id',
                'COALESCE(SUM(:payment_items.amount * :payment_items.count), 0) AS subscription_payments_amount',
            ]))
            ->joinWhere(
                tableChain: ':payment_items',
                condition: "payments.id = :payment_items.payment_id AND :payment_items.type IN (?)",
                params: $subscriptionTypeItem,
            )
            ->where([
                'user.deleted_at IS NULL',
                'payments.status IN (?)' => self::PAYMENT_STATUSES,
                'payments.paid_at > ?' => $paymentPaidAt,
                ':payment_items.id IS NOT NULL',
            ])
            ->group('payments.user_id');

        if (isset($userIDs)) {
            $this->line("  * computing '<info>subscription_payments_amount</info>' for provided user IDs");
            $paymentsQuery->where(['payments.user_id IN (?)' => $userIDs]);
        } elseif (isset($interval)) {
            $this->line("  * computing '<info>subscription_payments_amount</info>' for user IDs between [<info>{$interval[0]}</info>, <info>{$interval[1]}</info>]");
            $paymentsQuery->where(['payments.user_id BETWEEN ? AND ?' => $interval]);
        } else {
            throw new \RuntimeException('Either array of user IDs (e.g. [1,2,3,4,...]) or interval of user IDs (e.g. [1,1000]) need to be provided');
        }

        if (!empty($this->excludedSubscriptionTypeIds)) {
            $paymentsQuery->where(['payments.subscription_type_id NOT IN (?)' => $this->excludedSubscriptionTypeIds,]);
        }

        $values = $paymentsQuery->fetchPairs('user_id', 'subscription_payments_amount');

        $this->userStatsRepository->upsertUsersValues('subscription_payments_amount', $values);
    }

    public function computeUserAvgPaymentAmount(array $userIDs = null, array $interval = null)
    {
        $paymentPaidAt = $this->startDate;

        $paymentsQuery = $this->paymentsRepository->getTable()
            ->select(implode(',', [
                'payments.user_id AS user_id',
                "COALESCE(AVG((payments.amount / subscription_type.length) * {$this->subscriptionPeriod}), 0) AS avg_month_payment_amount",
            ]))
            ->where([
                'user.deleted_at IS NULL',
                'payments.status IN (?)' => self::PAYMENT_STATUSES,
                'payments.paid_at > ?' => $paymentPaidAt,
                'subscription_type.id IS NOT NULL',
            ])
            ->group('payments.user_id');

        if (isset($userIDs)) {
            $this->line("  * computing '<info>avg_month_payment</info>' for provided user IDs");
            $paymentsQuery->where(['payments.user_id IN (?)' => $userIDs]);
        } elseif (isset($interval)) {
            $this->line("  * computing '<info>avg_month_payment</info>' for user IDs between [<info>{$interval[0]}</info>, <info>{$interval[1]}</info>]");
            $paymentsQuery->where(['payments.user_id BETWEEN ? AND ?' => $interval]);
        } else {
            throw new \RuntimeException('Either array of user IDs (e.g. [1,2,3,4,...]) or interval of user IDs (e.g. [1,1000]) need to be provided');
        }

        if (!empty($this->excludedSubscriptionTypeIds)) {
            $paymentsQuery->where(['payments.subscription_type_id NOT IN (?)' => $this->excludedSubscriptionTypeIds,]);
        }

        $values = $paymentsQuery->fetchPairs('user_id', 'avg_month_payment_amount');

        $this->userStatsRepository->upsertUsersValues('avg_month_payment', $values);
    }
}
