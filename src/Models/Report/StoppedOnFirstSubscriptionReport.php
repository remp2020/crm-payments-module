<?php

namespace Crm\PaymentsModule\Report;

use Crm\SubscriptionsModule\Models\Report\BaseReport;
use Crm\SubscriptionsModule\Models\Report\ReportGroup;

class StoppedOnFirstSubscriptionReport extends BaseReport
{
    public function getData(ReportGroup $group, $params)
    {
        $query = <<<QUERY
SELECT {$group->groupField()} AS `key`, COUNT(*) AS `value`
FROM subscriptions
INNER JOIN users on users.id = subscriptions.user_id
INNER JOIN payments ON payments.subscription_id = subscriptions.id AND payments.status='paid'
INNER JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id AND payment_gateways.is_recurrent = 1
INNER JOIN recurrent_payments ON recurrent_payments.parent_payment_id = payments.id  AND recurrent_payments.state NOT IN ('active', 'charged')
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
            'label' => $this->translator->translate('payments.admin.report.stopped_on_first_subscription.label')
        ];
    }
}
