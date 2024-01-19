<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Database\Table\ActiveRow;
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

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
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
