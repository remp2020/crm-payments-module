<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class RecurrentPaymentStatusCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'recurrent_payment_status';

    private $recurrentPaymentsRepository;

    public function __construct(
        RecurrentPaymentsRepository $paymentsRepository
    ) {
        $this->recurrentPaymentsRepository = $paymentsRepository;
    }

    public function params(): array
    {
        $statuses = $this->recurrentPaymentsRepository->getStatusPairs();

        return [
            new StringLabeledArrayParam('recurrent_payment_status', 'Recurrent payment status', $statuses),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('recurrent_payments.status IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Recurrent payment status';
    }
}
