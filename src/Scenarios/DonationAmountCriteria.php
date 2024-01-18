<?php

namespace Crm\PaymentsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Criteria\ScenarioParams\NumberParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\Models\PaymentItem\DonationPaymentItem;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class DonationAmountCriteria implements ScenariosCriteriaInterface
{
    const KEY = 'payment_donation_amount';

    private $applicationConfig;

    private $translator;

    public function __construct(
        ApplicationConfig $applicationConfig,
        Translator $translator
    ) {
        $this->applicationConfig = $applicationConfig;
        $this->translator = $translator;
    }

    public function params(): array
    {
        return [
            new NumberParam(
                self::KEY,
                $this->translator->translate('payments.admin.scenarios.donation_amount.label'),
                $this->applicationConfig->get('currency'),
                ['>', '=']
            ),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $operator = $paramValues[self::KEY]->operator;
        $amount = $paramValues[self::KEY]->selection;

        $selection->where(
            ":payment_items.type = ? AND :payment_items.amount {$operator} ?",
            DonationPaymentItem::TYPE,
            $amount
        );
        return true;
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.donation_amount.label');
    }
}
