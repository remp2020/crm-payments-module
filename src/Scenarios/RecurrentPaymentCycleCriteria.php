<?php

namespace Crm\PaymentsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;

class RecurrentPaymentCycleCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'recurrent_payment_cycle';

    private const OPERATORS = ['=', '>', '<', '<=', '>='];

    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        return [
            new NumberParam(self::KEY, $this->translator->translate('payments.admin.scenarios.recurrent_payment_cycle.label'), 'Cycle', self::OPERATORS, ['min' => 0]),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        $operator = $values->operator;
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \Exception("Operator $operator is not a valid operator out of: " . Json::encode(self::OPERATORS));
        }

        $selection->where('recurrent_payments.cycle ' . $operator . ' ?', $values->selection);
        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.recurrent_payment_cycle.label');
    }
}
