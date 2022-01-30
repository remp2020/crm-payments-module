<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class PaymentGatewayCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'payment_gateway';

    private $paymentsRepository;

    private $paymentGatewaysRepository;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository
    ) {
        $this->paymentsRepository = $paymentsRepository;
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
