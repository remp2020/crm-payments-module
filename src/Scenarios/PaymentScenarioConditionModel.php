<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelRequirementsInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\ScenariosModule\Events\ConditionCheckException;

class PaymentScenarioConditionModel implements ScenarioConditionModelInterface, ScenarioConditionModelRequirementsInterface
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
    ) {
    }

    public function getInputParams(): array
    {
        return ['payment_id'];
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->payment_id)) {
            throw new ConditionCheckException("Payment scenario conditional model requires 'payment_id' job param.");
        }

        return $this->paymentsRepository->getTable()->where(['payments.id' => $scenarioJobParameters->payment_id]);
    }
}
