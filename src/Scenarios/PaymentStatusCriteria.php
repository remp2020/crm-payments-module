<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Database\Table\ActiveRow;
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

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues['status'];
        $selection->where('status IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Payment status';
    }
}
