<?php

namespace Crm\PaymentsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class RecurrentPaymentCardExpiredCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'recurrent_payment_card_expired';

    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        if ($values->selection) {
            $selection->where("expires_at IS NOT NULL AND expires_at < charge_at");
        } else {
            $selection->where("expires_at IS NULL OR expires_at >= charge_at");
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.recurrent_payment_card_expired.label');
    }
}
