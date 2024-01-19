<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentIsRecurrentChargeCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is-recurrent-charge';

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        $selection->where('payments.recurrent_charge = ?', $values->selection);

        return true;
    }

    public function label(): string
    {
        return 'Is recurrent charge';
    }
}
