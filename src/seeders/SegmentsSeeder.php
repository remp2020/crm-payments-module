<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SubscriptionsModule\Repository\SubscriptionsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    private $segmentsRepository;

    private $segmentGroupsRepository;

    /** @var OutputInterface */
    private $output;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentGroupsRepository $segmentGroupsRepository
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $this->output = $output;

        $group = $this->segmentGroupsRepository->load('Default group');

        // ------------------------------------------------

        $subscriptionTypes = "'" . implode("', '", [
            SubscriptionsRepository::TYPE_UPGRADE,
            SubscriptionsRepository::TYPE_PREPAID,
            SubscriptionsRepository::TYPE_GIFT,
        ]) . "'";
        $segmentCode = 'active-subscription-with-payment';
        $segmentName = 'Active subscribers with payment';
        $tableName = 'users';
        $fields = 'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscription_types.name,subscriptions.note';
        $query = <<<SQL
SELECT %fields%
FROM %table%

INNER JOIN subscriptions
  ON subscriptions.user_id = users.id
  AND subscriptions.start_time < NOW()
  AND subscriptions.end_time > NOW()

INNER JOIN subscription_types
  ON  subscription_types.id = subscriptions.subscription_type_id

LEFT JOIN payments
  ON payments.subscription_id = subscriptions.id

WHERE %where% 
  AND %table%.active=1
  AND (payments.id IS NOT NULL OR subscriptions.type IN ({$subscriptionTypes}))

GROUP BY %table%.id
SQL;
        $this->seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group);

        // ------------------------------------------------

        $subscriptionTypes = "'" . implode("', '", [
            SubscriptionsRepository::TYPE_UPGRADE,
            SubscriptionsRepository::TYPE_PREPAID,
            SubscriptionsRepository::TYPE_GIFT,
        ]) . "'";
        $segmentCode = 'active-subscription-without-payment';
        $segmentName = 'Active subscribers without payment';
        $tableName = 'users';
        $fields = 'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscription_types.name,subscriptions.note';
        $query = <<<SQL
SELECT %fields%
FROM %table%

INNER JOIN subscriptions
  ON subscriptions.user_id = users.id
  AND subscriptions.start_time < NOW()
  AND subscriptions.end_time > NOW()

INNER JOIN subscription_types
  ON  subscription_types.id = subscriptions.subscription_type_id

LEFT JOIN payments
  ON payments.subscription_id = subscriptions.id

WHERE %where%
  AND %table%.active=1
  AND payments.id IS NULL
  AND subscriptions.type NOT IN ({$subscriptionTypes})
  AND users.id NOT IN (
     SELECT subscriptions.user_id
     FROM subscriptions
     LEFT JOIN payments on subscriptions.id = payments.subscription_id
     INNER JOIN users ON users.id = subscriptions.user_id AND users.active=1
     WHERE (payments.id IS NOT NULL OR subscriptions.type IN ({$subscriptionTypes}))
     AND  start_time < NOW()
     AND  end_time > NOW()
)

GROUP BY %table%.id
SQL;
        $this->seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group);
    }

    private function seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group)
    {
        if (!$this->segmentsRepository->findByCode($segmentCode)) {
            $this->segmentsRepository->add(
                $segmentName,
                1,
                $segmentCode,
                $tableName,
                $fields,
                $query,
                $group
            );
            $this->output->writeln("  <comment>* segment <info>{$segmentCode}</info> created</comment>");
        } else {
            $this->output->writeln("  * segment <info>{$segmentCode}</info> exists");
        }
    }
}
