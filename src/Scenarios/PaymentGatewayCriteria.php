<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Models\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Models\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentGatewayCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'payment_gateway';

    private $paymentGatewaysRepository;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
    ) {
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
    }

    public function params(): array
    {
        $pairs = $this->paymentGatewaysRepository->getAllVisible()->fetchPairs('code', 'name');
        return [
            new StringLabeledArrayParam(self::KEY, 'Payment gateway', $pairs),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where('payment_gateway.code IN (?)', $values->selection);
        return true;
    }

    public function label(): string
    {
        return 'Payment gateway';
    }
}
