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

    public function addCondition(Selection $selection, $key, $values)
    {
        $selection->where('status IN (?)', $values->selection);
    }

    public function label(): string
    {
        return 'Payment status';
    }
}
