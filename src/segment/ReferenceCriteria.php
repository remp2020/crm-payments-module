<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\SegmentModule\Params\ParamsBag;
use Crm\SegmentModule\Params\StringParam;

class ReferenceCriteria implements CriteriaInterface
{
    public function label(): string
    {
        return "Reference (VS)";
    }

    public function category(): string
    {
        return "Payment";
    }

    public function params(): array
    {
        return [
            new StringParam('reference', "Reference", "Payments with specified references (variable_symbols)"),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        if ($params->has('reference')) {
            $where[] = " payments.variable_symbol = {$params->string('reference')->escapedString()}";
        }

        return "SELECT DISTINCT(payments.id) AS id
          FROM payments
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $params): string
    {
        return " reference {$params->string('reference')->value()}";
    }

    public function fields(): array
    {
        return [];
    }
}
