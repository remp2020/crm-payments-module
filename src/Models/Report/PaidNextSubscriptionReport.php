<?php

namespace Crm\PaymentsModule\Models\Report;

use Crm\SubscriptionsModule\Models\Report\BaseReport;
use Crm\SubscriptionsModule\Models\Report\ReportGroup;
use Nette\Localization\Translator;

class PaidNextSubscriptionReport extends BaseReport
{
    private $paidCount;

    public function __construct($name, Translator $translator, $paidCount)
    {
        parent::__construct($name, $translator);
        $this->paidCount = $paidCount;
    }

    public function getData(ReportGroup $group, $params)
    {
        $nextPaymentQuery = '';
        $prevPaymentTable = 'recurrent_payments';
        for ($i = 1; $i < $this->paidCount; $i++) {
            $nextPaymentQuery .= "INNER JOIN recurrent_payments AS recurrent_payments_{$i} ON recurrent_payments_{$i}.parent_payment_id = $prevPaymentTable.payment_id AND recurrent_payments_{$i}.state = 'charged'";
            $prevPaymentTable = "recurrent_payments_{$i}";
        }

        $query = <<<QUERY
SELECT {$group->groupField()} AS `key`, COUNT(*) AS `value`
FROM subscriptions
INNER JOIN users on users.id = subscriptions.user_id
INNER JOIN payments ON payments.subscription_id = subscriptions.id AND payments.status='paid'
INNER JOIN payment_gateways ON payment_gateways.id = payments.payment_gateway_id AND payment_gateways.is_recurrent = 1
INNER JOIN recurrent_payments ON recurrent_payments.parent_payment_id = payments.id AND recurrent_payments.state = 'charged'

{$nextPaymentQuery}
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
            'label' => $this->translator->translate('payments.admin.report.paid_next_subscription.label', (int) $this->paidCount),
        ];
    }
}
