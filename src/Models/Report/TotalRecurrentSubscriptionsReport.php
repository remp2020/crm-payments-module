<?php

namespace Crm\PaymentsModule\Models\Report;

use Crm\SubscriptionsModule\Models\Report\BaseReport;
use Crm\SubscriptionsModule\Models\Report\ReportGroup;

class TotalRecurrentSubscriptionsReport extends BaseReport
{
    public function getData(ReportGroup $group, $params)
    {
        $query = <<<QUERY
SELECT {$group->groupField()} AS `key`, COUNT(*) AS `value`
FROM subscriptions
INNER JOIN users on users.id = subscriptions.user_id
INNER JOIN payments ON payments.subscription_id=subscriptions.id AND payments.status='paid'
INNER JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id AND payment_gateways.is_recurrent = 1
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
            'label' => $this->translator->translate('payments.admin.report.total_recurrent_subscriptions.label'),
        ];
    }
}
