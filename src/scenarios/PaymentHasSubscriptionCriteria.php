<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Kdyby\Translation\Translator;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;

class PaymentHasSubscriptionCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'payment_has_subscription';

    private $translator;

    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new BooleanParam(
                self::KEY,
                $this->translator->translate('payments.admin.scenarios.payment_has_subscription.label')
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];

        if ($values->selection) {
            $selection->where('payments.subscription_id IS NOT NULL');
        } else {
            $selection->where('payments.subscription_id IS NULL');
        }

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.payment_has_subscription.label');
    }
}
