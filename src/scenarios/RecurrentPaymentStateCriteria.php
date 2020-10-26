<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\Params\StringLabeledArrayParam;
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

    public function addCondition(Selection $selection, $values, IRow $criterionItemRow): bool
    {
        $selection->where('state IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Recurrent payment state';
    }
}
