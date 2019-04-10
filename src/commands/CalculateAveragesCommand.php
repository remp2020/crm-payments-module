<?php

namespace Crm\PaymentsModule\Commands;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SubscriptionsModule\PaymentItem\SubscriptionTypePaymentItem;
use Nette\Database\Context;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
            INSERT INTO user_meta (`user_id`,`key`,`value`,`created_at`,`updated_at`)
            SELECT
                id,
                'subscription_payments',
                (
                    SELECT COUNT(DISTINCT(payments.id))
                    FROM payments
                    INNER JOIN payment_items ON payment_items.payment_id = payments.id AND payment_items.type IN ('$subscriptionTypeItem')
                    WHERE
                        status='$paidStatus'
                        AND payments.user_id = users.id
                ),
                NOW(),
                NOW()
            FROM users
            ON DUPLICATE KEY UPDATE `updated_at`=NOW(), `value`=VALUES(value);
SQL
        );

        $this->database->query(<<<SQL
            INSERT INTO user_meta (`user_id`,`key`,`value`,`created_at`,`updated_at`)
            SELECT
                id,
                'subscription_payments_amount',
                (
                    SELECT SUM(payment_items.amount * payment_items.count)
                    FROM payments
                    INNER JOIN payment_items ON payment_items.payment_id = payments.id AND payment_items.type IN ('$subscriptionTypeItem')
                    WHERE
                        status='$paidStatus'
                        AND payments.user_id = users.id
                ),
                NOW(),
                NOW()
            FROM users
            ON DUPLICATE KEY UPDATE `updated_at`=NOW(), `value`=VALUES(value);
SQL
        );

        $this->database->query(<<<SQL
            INSERT INTO user_meta (`user_id`,`key`,`value`,`created_at`,`updated_at`)
            SELECT
                id,
                'avg_month_payment',
                (
                    SELECT COALESCE(AVG(payments.amount / subscription_types.length * 31), 0)
                    FROM payments
                    INNER JOIN subscription_types ON subscription_types.id = payments.subscription_type_id AND subscription_types.length > 0
                    WHERE
                        payments.status='$paidStatus'
                        AND payments.user_id = users.id
                ),
                NOW(),
                NOW()
            FROM users
            ON DUPLICATE KEY UPDATE `updated_at`=NOW(), `value`=VALUES(value);
SQL
        );
    }
}
