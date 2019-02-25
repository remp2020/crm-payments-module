<?php

namespace Crm\PaymentsModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\PaymentsModule\Components\SubscribersWithPaymentWidgetFactory;
use Crm\SegmentModule\Repository\SegmentGroupsRepository;
use Crm\SegmentModule\Repository\SegmentsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SegmentsSeeder implements ISeeder
{
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
        $code = SubscribersWithPaymentWidgetFactory::DEFAULT_SEGMENT;
        $segment = $this->segmentsRepository->findByCode($code);
        if (!$segment) {
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
  AND (payments.id IS NOT NULL OR subscriptions.type IN ('upgrade', 'prepaid', 'gift'))

GROUP BY %table%.id
SQL;

            $group = $this->segmentGroupsRepository->load('Default group');
            $segment = $this->segmentsRepository->add(
                'Active subscribers with payment',
                1,
                $code,
                'users',
                'users.id,users.email,users.first_name,users.last_name,subscriptions.type,subscription_types.name,subscriptions.note',
                $query,
                $group
            );
            $output->writeln("  <comment>* segment <info>{$code}</info> created</comment>");
        } else {
            $output->writeln("  * segment <info>{$code}</info> exists");
        }
    }
}
