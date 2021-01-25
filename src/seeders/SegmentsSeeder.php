<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Crm\SegmentModule\Seeders\SegmentsTrait;
use Nette\Utils\DateTime;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
    use SegmentsTrait;

    private $segmentsRepository;

    private $segmentGroupsRepository;

    public function __construct(
        SegmentsRepository $segmentsRepository,
        SegmentGroupsRepository $segmentGroupsRepository
    ) {
        $this->segmentsRepository = $segmentsRepository;
        $this->segmentGroupsRepository = $segmentGroupsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $tableName = 'users';
        $fields = 'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscriptions.note';

        // ------------------------------------------------

        $segmentCode = 'active-subscribers-with-paid-subscriptions';
        $now = new DateTime();
        $segment = $this->seedSegment(
            $output,
            'Active subscribers with paid subscriptions',
            $segmentCode,
            <<<SQL
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
SQL
            ,
            null,
            $tableName,
            $fields
        );

        // check if segment existed before this seed (compare formatted datetimes because DB entry doesn't have milliseconds)
        if ($now->format('Y-m-d H:i:s') <= $segment->created_at->format('Y-m-d H:i:s')) {
            // Ad-hoc copy values of segment 'active-subscription-with-payment' into 'active-subscribers-with-paid-subscriptions'
            // This is done because the new segment is a logical follow-up of the segment previously seeded in this seeder and we want to keep the history
            $sql = <<<SQL
INSERT INTO segments_values (`date`, `value`, `segment_id`)
  SELECT `date`, `value`, (SELECT id FROM segments WHERE code = '$segmentCode')
  FROM segments_values
  JOIN segments ON segments.id = segments_values.segment_id AND segments.code = 'active-subscription-with-payment'
SQL;
            $this->segmentsRepository->getDatabase()->query($sql);
            $output->writeln("    * segment values copied from old version of segment <info>active-subscription-with-payment</info> to new <info>{$segmentCode}</info>");
        }

        // ------------------------------------------------

        $segmentCode = 'active-subscribers-having-only-non-paid-subscriptions';
        $now = new DateTime();
        $segment = $this->seedSegment(
            $output,
            'Active subscribers having only non-paid subscriptions',
            $segmentCode,
            <<<SQL
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
SQL
            ,
            null,
            $tableName,
            $fields
        );

        // check if segment existed before this seed (compare formatted datetimes because DB entry doesn't have milliseconds)
        if ($now->format('Y-m-d H:i:s') <= $segment->created_at->format('Y-m-d H:i:s')) {
            // Ad-hoc copy values of segment 'active-subscription-without-payment' into 'active-subscribers-with-paid-subscriptions'
            // This is done because the new segment is a logical follow-up of the segment previously seeded in this seeder and we want to keep the history
            $sql = <<<SQL
INSERT INTO segments_values (`date`, `value`, `segment_id`)
  SELECT `date`, `value`, (SELECT id FROM segments WHERE code = '$segmentCode')
  FROM segments_values
  JOIN segments ON segments.id = segments_values.segment_id AND segments.code = 'active-subscription-without-payment'
SQL;
            $this->segmentsRepository->getDatabase()->query($sql);
            $output->writeln("    * segment values copied from old version of segment <info>active-subscribers-with-paid-subscriptions</info> to new <info>{$segmentCode}</info>");
        }
    }
}
