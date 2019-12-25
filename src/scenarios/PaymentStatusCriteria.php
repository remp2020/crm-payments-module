<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Database\Table\Selection;

class PaymentStatusCriteria implements ScenariosCriteriaInterface
{
    private $paymentsRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
    }

    public function params(): array
    {
        $statuses = $this->paymentsRepository->getStatusPairs();

        return [
            new StringLabeledArrayParam('status', 'Payment status', $statuses),
        ];
    }

    public function addCondition(Selection $selection): Selection
    {
        return $selection;
        // TODO
        //$where = [];
        //
        //$where[] = " payments.status IN ({$params->stringArray('code')->escapedString()}) ";
        //
        //return "SELECT DISTINCT(payments.id) AS id
        //  FROM payments
        //  WHERE " . implode(" AND ", $where);
    }

    public function label(): string
    {
        return 'Payment status';
    }
}
