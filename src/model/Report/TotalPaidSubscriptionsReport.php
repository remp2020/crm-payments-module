<?php

namespace Crm\PaymentsModule\Report;

use Crm\SubscriptionsModule\Report\BaseReport;
use Crm\SubscriptionsModule\Report\ReportGroup;

class TotalPaidSubscriptionsReport extends BaseReport
{
    public function getData(ReportGroup $group, $params)
    {
        $query = <<<QUERY
SELECT {$group->groupField()} AS `key`, COUNT(*) AS `value`
FROM subscriptions
INNER JOIN users on users.id = subscriptions.user_id
INNER JOIN payments ON payments.subscription_id=subscriptions.id AND payments.status='paid'
WHERE
  subscriptions.subscription_type_id={$params['subscription_type_id']}
GROUP BY {$group->groupField()}
QUERY;
        $data = $this->getDatabase()->query($query);
        $result = [];
        foreach ($data as $row) {
            $result[$row->key] = $row->value;
        }
        return [
            'id' => $this->getId(),
            'key' => __CLASS__,
            'data' => $result,
            'label' => 'pouzili akciu'
        ];
    }
}
