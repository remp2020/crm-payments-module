<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioConditionModelInterface;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;

class RecurrentPaymentScenarioConditionModel implements ScenarioConditionModelInterface
{
    public function __construct(
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
    }

    public function getItemQuery($scenarioJobParameters): Selection
    {
        if (!isset($scenarioJobParameters->recurrent_payment_id)) {
            throw new Exception("Recurrent payment scenario conditional model requires 'recurrent_payment_id' job param.");
        }

        return $this->recurrentPaymentsRepository->getTable()->where(['recurrent_payments.id' => $scenarioJobParameters->recurrent_payment_id]);
    }
}
