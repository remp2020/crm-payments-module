<?php

namespace Crm\PaymentsModule\Segment;

use Crm\ApplicationModule\Criteria\CriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\SegmentModule\Models\Params\ParamsBag;
use Crm\SegmentModule\Models\Params\StringArrayParam;

class StatusCriteria implements CriteriaInterface
{
    private $paymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
    }

    public function label(): string
    {
        return "Status";
    }

    public function category(): string
    {
        return "Payment";
    }

    public function params(): array
    {
        return [
            new StringArrayParam('status', "Status", "Filters payments with selected statuses", true, null, null, array_keys($this->paymentsRepository->getStatusPairs())),
        ];
    }

    public function join(ParamsBag $params): string
    {
        $where = [];

        $where[] = " payments.status IN ({$params->stringArray('status')->escapedString()}) ";

        return "SELECT DISTINCT(payments.id) AS id
          FROM payments
          WHERE " . implode(" AND ", $where);
    }

    public function title(ParamsBag $params): string
    {
        return " status {$params->stringArray('status')->escapedString()}";
    }

    public function fields(): array
    {
        return [];
    }
}
