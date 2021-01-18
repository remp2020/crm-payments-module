<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
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

        $group = $this->segmentGroupsRepository->findByCode('default-group');

        // ------------------------------------------------

        $segmentCode = 'active-subscribers-with-paid-subscriptions';
        $segmentName = 'Active subscribers with paid subscriptions';
        $tableName = 'users';
        $fields = 'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscriptions.note';
        $query = <<<SQL
SELECT %fields%
FROM %table%

INNER JOIN subscriptions
  ON subscriptions.user_id = users.id
  AND subscriptions.start_time <= NOW() 
  AND subscriptions.end_time > NOW()
  AND subscriptions.is_paid = 1 

WHERE %where% 
  AND %table%.active=1

GROUP BY %table%.id
SQL;
        $segmentCreated = $this->seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group);
        if ($segmentCreated) {
            // Ad-hoc copy values of segment 'active-subscription-with-payment' into 'active-subscribers-with-paid-subscriptions'
            // This is done because the new segment is a logical follow-up of the segment previously seeded in this seeder and we want to keep the history
            $sql = <<<SQL
INSERT INTO segments_values (`date`, `value`, `segment_id`)
  SELECT `date`, `value`, (SELECT id FROM segments WHERE code = '$segmentCode')
  FROM segments_values
  JOIN segments ON segments.id = segments_values.segment_id AND segments.code = 'active-subscription-with-payment'
SQL;
            $this->segmentsRepository->getDatabase()->query($sql);
        }

        // ------------------------------------------------

        $segmentCode = 'active-subscribers-having-only-non-paid-subscriptions';
        $segmentName = 'Active subscribers having only non-paid subscriptions';
        $tableName = 'users';
        $fields = 'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscriptions.note';
        $query = <<<SQL
SELECT %fields%
FROM %table%

INNER JOIN subscriptions
  ON subscriptions.user_id = users.id
  AND subscriptions.start_time <= NOW() 
  AND subscriptions.end_time > NOW()

WHERE %where% 
  AND %table%.active=1

GROUP BY %table%.id

HAVING SUM(subscriptions.is_paid) = 0
/* HAVING SUM value is 0 only in case when given user has exactly 0 paid subscriptions (otherwise > 0, therefore not matching the condition) */ 
SQL;
        $segmentCreated = $this->seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group);
        if ($segmentCreated) {
            $sql = <<<SQL
INSERT INTO segments_values (`date`, `value`, `segment_id`)
  SELECT `date`, `value`, (SELECT id FROM segments WHERE code = '$segmentCode')
  FROM segments_values
  JOIN segments ON segments.id = segments_values.segment_id AND segments.code = 'active-subscription-without-payment'
SQL;
            $this->segmentsRepository->getDatabase()->query($sql);
        }
    }

    private function seedSegment($segmentCode, $segmentName, $tableName, $query, $fields, $group): bool
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
            return true;
        }

        $this->output->writeln("  * segment <info>{$segmentCode}</info> exists");
        return false;
    }
}
