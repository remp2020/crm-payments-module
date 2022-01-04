<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Repository\PaymentItemsRepository;
use Nette\Database\Table\IRow;
use Nette\Database\Table\Selection;
use Nette\Localization\ITranslator;

class PaymentHasItemTypeCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'payment_has_item_type_criteria';

    private ITranslator $translator;

    private PaymentItemsRepository $paymentItemsRepository;

    public function __construct(ITranslator $translator, PaymentItemsRepository $paymentItemsRepository)
    {
        $this->translator = $translator;
        $this->paymentItemsRepository = $paymentItemsRepository;
    }

    public function params(): array
    {
        $pairs = $this->paymentItemsRepository->getTypes();
        return [
            new StringLabeledArrayParam(self::KEY, 'Payment item type', $pairs),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, IRow $criterionItemRow): bool
    {
        $values = $paramValues[self::KEY];
        $selection->where(':payment_items.type IN (?)', $values->selection);

        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.payment_has_item_type.label');
    }
}
