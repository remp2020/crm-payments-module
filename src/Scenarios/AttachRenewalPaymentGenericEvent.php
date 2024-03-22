<?php

namespace Crm\PaymentsModule\Scenarios;

use Crm\PaymentsModule\Events\AttachRenewalPaymentEvent;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;

class AttachRenewalPaymentGenericEvent implements ScenarioGenericEventInterface
{
    public function getLabel(): string
    {
        return 'Create new (or reuse) renewal payment for notification.';
    }

    public function getParams(): array
    {
        return [];
    }

    public function createEvents($options, $params): array
    {
        return [
            new AttachRenewalPaymentEvent($params->subscription_id, $params->user_id),
        ];
    }
}
