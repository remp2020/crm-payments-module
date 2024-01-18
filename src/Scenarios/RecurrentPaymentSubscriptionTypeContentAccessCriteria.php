<?php

namespace Crm\PaymentsModule\Scenarios;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\Criteria\ScenarioParams\StringLabeledArrayParam;
use Crm\ApplicationModule\Criteria\ScenariosCriteriaInterface;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\SubscriptionsModule\Repositories\ContentAccessRepository;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

class RecurrentPaymentSubscriptionTypeContentAccessCriteria implements ScenariosCriteriaInterface
{
    public const KEY = 'recurrent_payment_subscription_type_content_access';

    public function __construct(
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
        private ContentAccessRepository $contentAccessRepository,
        private Translator $translator,
    ) {
    }

    public function params(): array
    {
        $contentAccess = $this->contentAccessRepository->all()->fetchPairs('name', 'description');
        return [
            new StringLabeledArrayParam(self::KEY, $this->translator->translate('payments.admin.scenarios.recurrent_payment_subscription_type_content_access.param_label'), $contentAccess),
        ];
    }

    public function addConditions(Selection $selection, array $paramValues, ActiveRow $criterionItemRow): bool
    {
        $selectedValues = $paramValues[self::KEY]->selection;
        $subscriptionType = $this->recurrentPaymentsResolver->resolveSubscriptionType($criterionItemRow);
        return $this->contentAccessRepository->hasOneOfAccess($subscriptionType, $selectedValues);
    }

    public function label(): string
    {
        return $this->translator->translate('payments.admin.scenarios.recurrent_payment_subscription_type_content_access.label');
    }
}
