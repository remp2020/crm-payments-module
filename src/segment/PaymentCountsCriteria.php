<?php

namespace Crm\PaymentsModule\Segment;

use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Criteria\Fields;
use Crm\SegmentModule\Params\NumberParam;
use Crm\SegmentModule\Params\ParamsBag;

class PaymentCountsCriteria implements CriteriaInterface
{
    private $paymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
    }

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
                "Filters users with specific amount of payments for subscriptions"
            ),
        ];
    }

    public function join(ParamsBag $paramBag): string
    {
        $where = [];
        $join = [];

        if ($paramBag->has('subscription_payments')) {
            $join[] = "LEFT JOIN user_meta spum ON `key` = 'subscription_payments' AND spum.user_id = payments.user_id";
            $where += $paramBag->number('subscription_payments')->escapedConditions('spum.value');
        }

        return "SELECT DISTINCT(payments.user_id) AS id, " . Fields::formatSql($this->fields()) . "
          FROM payments " .
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
            'payments.id' => 'payment_id',
            'payments.status' => 'payment_status',
            'payments.variable_symbol' => 'variable_symbol',
            'payments.amount' => 'amount',
            'payments.additional_amount' => 'additional_amount',
            'payments.additional_type' => 'additional_type',
            'payments.created_at' => 'created_at',
            'payments.paid_at' => 'paid_at',
            'payments.recurrent_charge' => 'recurrent_charge',
        ];
    }
}
