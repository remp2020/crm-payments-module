<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class RecurrentPaymentStateCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'recurrent_payment_state';

    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params(): array
    {
        $states = $this->recurrentPaymentsRepository->getStates();

        return [
            new StringLabeledArrayParam(self::KEY, 'State', array_combine($states, $states)),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('state IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Recurrent payment state';
    }
}
