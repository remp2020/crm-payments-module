<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Models\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Criteria\Fields;
use Crm\SegmentModule\Models\Params\NumberParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class PaymentCountsCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Payment Counts";
    }

    public function category(): string
    {
        return "Payment";
    }

    public function params(): array
    {
        return [
            new NumberParam(
                "subscription_payments",
                "Subscription payments",
                "Filters users with specific amount of payments for subscriptions",
            ),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];
        $join = [];

        if ($params->has('subscription_payments')) {
            $join[] = "LEFT JOIN user_stats spus ON `key` = 'subscription_payments' AND spus.user_id = users.id";
            $where += $params->number('subscription_payments')->conditions('COALESCE(`spus`.`value`, 0)');
        }

        return "SELECT DISTINCT(users.id) AS id, " . Fields::formatSql($this->fields()) . "
          FROM users " .
          implode("\n", $join) . "
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $paramBag): string
    {
        $result = '';

        if ($paramBag->has('subscription_payments')) {
            $result .= " with {$paramBag->number('subscription_payments')->title()} subscription payments";
        }
        return $result;
    }

    public function fields(): array
    {
        return [
            'users.id' => 'payment_id',
        ];
    }
}
