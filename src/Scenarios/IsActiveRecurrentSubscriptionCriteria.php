<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\BooleanParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class IsActiveRecurrentSubscriptionCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'is_active_recurrent_subscription';

    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params(): array
    {
        return [
            new BooleanParam(self::KEY, $this->label()),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $subscriptionRow): bool
    {
        $values = $paramValues[self::KEY];

        $isActiveRecurrentSubscriptionCondition = $subscriptionRow->is_recurrent
            && !$this->recurrentPaymentsRepository->isStoppedBySubscription($subscriptionRow);

        if ($values->selection && !$isActiveRecurrentSubscriptionCondition) {
            return false;
        }

        if (!$values->selection && $isActiveRecurrentSubscriptionCondition) {
            return false;
        }

        return true;
    }

    public function label(): string
    {
        return 'Is active recurrent subscription';
    }
}
