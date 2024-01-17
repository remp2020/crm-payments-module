<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Models\Params\DecimalParam;
use Crm\SegmentModule\Models\Params\ParamsBag;

class AmountCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Payment";
    }

    public function category(): string
    {
        return "Payment";
    }

    public function params(): array
    {
        return [
            new DecimalParam('amount', "Single payment amount", "Filters payments which amount matches the condition"),
            new DecimalParam('additional_amount', "Single payment donation", "Filters payments which donation matches the condition"),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('amount')) {
            $where += $params->decimal('amount')->escapedConditions('payments.amount');
        }

        if ($params->has('additional_amount')) {
            $where += $params->decimal('additional_amount')->escapedConditions('payments.additional_amount');
        }

        return "SELECT DISTINCT(payments.id) AS id
          FROM payments
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $params): string
    {
        $title = '';
        if ($params->has('amount')) {
            $title .= " amount {$params->decimal('amount')->title()}";
        }

        if ($params->has('additional_amount')) {
            $title .= " additional amount {$params->decimal('additional_amount')->title()}";
        }
        return $title;
    }

    public function fields(): array
    {
        return [];
    }
}
