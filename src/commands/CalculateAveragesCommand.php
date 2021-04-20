<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Nette\Database\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Calculates average and total amounts of money spent and stores it in user's meta data.
 *
 * These meta data are mainly used by admin widget TotalUserPayments.
 */
class CalculateAveragesCommand extends \Symfony\Component\Console\Command\Command
{
    private $database;

    public function __construct(Context $database)
    {
        parent::__construct();
        $this->database = $database;
    }

    protected function configure()
    {
        $this->setName('payments:calculate_averages')
            ->setDescription('Calculate payment-related averages');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $paidStatus = PaymentsRepository::STATUS_PAID;
        $subscriptionTypeItem = SubscriptionTypePaymentItem::TYPE;

        $this->database->query(<<<SQL
            -- fill empty values for new users
            INSERT IGNORE INTO `user_meta` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
            SELECT `id`, 'subscription_payments', 0, NOW(), NOW()
            FROM `users`;

            -- calculate & update values
            UPDATE `user_meta`
            INNER JOIN (
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COUNT(DISTINCT(`payments`.`id`)) AS `subscription_payments_count`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` = '$paidStatus'
                WHERE `payment_items`.`type` IN ('$subscriptionTypeItem')
                GROUP BY `payments`.`user_id`
            ) AS `subscription_payments`
                ON `user_meta`.`user_id` = `subscription_payments`.`user_id`
            SET
               `value` = `subscription_payments`.`subscription_payments_count`,
               `updated_at` = NOW()
            WHERE
                `key` = 'subscription_payments'
            ;
SQL
        );

        $this->database->query(<<<SQL
            -- fill empty values for new users
            INSERT IGNORE INTO `user_meta` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
            SELECT `id`, 'subscription_payments_amount', 0, NOW(), NOW()
            FROM `users`;

            -- calculate & update values
            UPDATE `user_meta`
            INNER JOIN (
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COALESCE(SUM(`payment_items`.`amount` * `payment_items`.`count`), 0) AS `subscription_payments_amount`
                FROM `payment_items`
                INNER JOIN `payments`
                    ON `payments`.`id` = `payment_items`.`payment_id`
                    AND `payments`.`status` = '$paidStatus'
                WHERE `payment_items`.`type` IN ('$subscriptionTypeItem')
                GROUP BY `payments`.`user_id`
            ) AS `subscription_payments`
                ON `user_meta`.`user_id` = `subscription_payments`.`user_id`
            SET
               `value` = `subscription_payments`.`subscription_payments_amount`,
               `updated_at` = NOW()
            WHERE
                `key` = 'subscription_payments_amount'
            ;
SQL
        );

        $this->database->query(<<<SQL
            -- fill empty values for new users
            INSERT IGNORE INTO `user_meta` (`user_id`,`key`,`value`, `created_at`, `updated_at`)
            SELECT `id`, 'avg_month_payment', 0, NOW(), NOW()
            FROM `users`;

            -- calculate & update values
            UPDATE `user_meta`
            INNER JOIN (
                SELECT
                    `payments`.`user_id` AS `user_id`,
                    COALESCE(AVG((`payments`.`amount` / `subscription_types`.`length`) * 31), 0) AS `avg_month_payment_amount`
                FROM `payments`
                INNER JOIN `subscription_types`
                   ON `subscription_types`.`id` = `payments`.`subscription_type_id`
                   AND `subscription_types`.`length` > 0
                WHERE
                    `payments`.`status` = '$paidStatus'
                GROUP BY `payments`.`user_id`
            ) AS `avg_month_payment`
                ON `user_meta`.`user_id` = `avg_month_payment`.`user_id`
            SET
               `value` = `avg_month_payment`.`avg_month_payment_amount`,
               `updated_at` = NOW()
            WHERE
                `key` = 'avg_month_payment'
            ;
SQL
        );

        return 0;
    }
}
