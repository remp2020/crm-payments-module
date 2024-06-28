<?php

declare(strict_types=1);

namespace Crm\PaymentsModule\Scenarios;

use Crm\PaymentsModule\Events\StopRecurrentPaymentEvent;
use Crm\ScenariosModule\Events\ScenarioGenericEventInterface;

class StopRecurrentPaymentGenericEvent implements ScenarioGenericEventInterface
{
    public function getLabel(): string
    {
        return 'Stop recurrent payment';
    }

    public function getParams(): array
    {
        return [];
    }

    public function createEvents($options, $params): array
    {
        return [
            new StopRecurrentPaymentEvent($params->recurrent_payment_id),
        ];
    }
}
