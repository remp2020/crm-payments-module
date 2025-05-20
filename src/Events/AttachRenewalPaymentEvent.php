<?php

namespace Crm\PaymentsModule\Events;

use Crm\ScenariosModule\Events\ScenarioGenericEventAdditionalParamsInterface;
use League\Event\AbstractEvent;

class AttachRenewalPaymentEvent extends AbstractEvent implements ScenarioGenericEventAdditionalParamsInterface
{
    private array $additionalScenarioJobParameters = [];

    public function __construct(
        private $subscriptionId,
        private $userId,
    ) {
    }

    public function getSubscriptionId()
    {
        return $this->subscriptionId;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getAdditionalJobParameters(): array
    {
        return $this->additionalScenarioJobParameters;
    }

    public function setAdditionalJobParameters(array $params): void
    {
        $this->additionalScenarioJobParameters = $params;
    }
}
