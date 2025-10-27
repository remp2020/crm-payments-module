<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;

class SubscriptionPausedEvent extends AbstractEvent
{
    public function __construct(
        private readonly ActiveRow $subscription,
        private readonly \DateTime $pauseAt,
        private readonly \DateTime $resumeAt,
    ) {
    }

    public function getSubscription(): ActiveRow
    {
        return $this->subscription;
    }

    public function getPauseAt(): \DateTime
    {
        return $this->pauseAt;
    }

    public function getResumeAt(): \DateTime
    {
        return $this->resumeAt;
    }
}
