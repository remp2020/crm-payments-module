<?php

namespace Crm\PaymentsModule\Models\Report;

use Crm\SubscriptionsModule\Models\Report\BaseReport;
use Crm\SubscriptionsModule\Models\Report\ReportGroup;

class NoRecurrentChargeReport extends BaseReport
{
    public function getData(ReportGroup $group, $params)
    {
        //  AND recurrent_payments.status IS NULL
        $query = <<<QUERY
SELECT {$group->groupField()} AS `key`, COUNT(*) AS `value`
FROM subscriptions
INNER JOIN users ON users.id = subscriptions.user_id
INNER JOIN payments ON payments.subscription_id = subscriptions.id AND payments.status='paid'
INNER JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id AND payment_gateways.is_recurrent = 1
INNER JOIN recurrent_payments ON recurrent_payments.parent_payment_id = payments.id AND recurrent_payments.state = 'active' 
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
            'label' => $this->translator->translate('payments.admin.report.no_recurrent_charge.label'),
        ];
    }
}
