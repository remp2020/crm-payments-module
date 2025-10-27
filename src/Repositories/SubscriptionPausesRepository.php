<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Repositories;

use Crm\ApplicationModule\Models\Database\Repository;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\PaymentsModule\Events\SubscriptionPausedEvent;
use League\Event\Emitter;
use Nette\Caching\Storage;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Json;

class SubscriptionPausesRepository extends Repository
{
    use NowTrait;

    protected $tableName = 'subscription_pauses';

    public function __construct(
        Explorer $database,
        Storage $cacheStorage = null,
        private readonly Emitter $emitter,
    ) {
        parent::__construct($database, $cacheStorage);
    }

    final public function all(): Selection
    {
        return $this->getTable();
    }

    final public function add(
        ActiveRow $subscription,
        \DateTime $pauseAt,
        \DateTime $resumeAt,
        array $changes,
    ): ActiveRow|bool {
        $row = $this->insert([
            'subscription_id' => $subscription->id,
            'pause_at' => $pauseAt,
            'resume_at' => $resumeAt,
            'changes' => Json::encode($changes),
            'created_at' => $this->getNow(),
        ]);

        if ($row) {
            $this->emitter->emit(new SubscriptionPausedEvent(
                $subscription,
                $pauseAt,
                $resumeAt,
            ));
        }

        return $row;
    }
}
