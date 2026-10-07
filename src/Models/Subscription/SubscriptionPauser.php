<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Subscription;

use Crm\ApplicationModule\Database\DatabaseTransaction;
use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Models\NowTrait;
use Crm\ApplicationModule\Models\ResettableInterface;
use Crm\PaymentsModule\DataProviders\IsSubscriptionPausableDataProviderInterface;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Events\SubscriptionShortenedEvent;
use Crm\SubscriptionsModule\Models\Subscription\StopSubscriptionHandler;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionMetaRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use League\Event\Emitter;
use Nette\Database\Table\ActiveRow;
use Throwable;
use Tracy\Debugger;

class SubscriptionPauser implements ResettableInterface
{
    use NowTrait;

    const string ACTIVE_SUBSCRIPTION_CHANGES = 'active_subscription_changes';
    const string FUTURE_SUBSCRIPTION_CHANGES = 'future_subscription_changes';
    const string RECURRENT_PAYMENTS_CHANGES = 'recurrent_payments_changes';

    const string NEW_SUBSCRIPTION = 'new_subscription';
    const string STOPPED_SUBSCRIPTION = 'stopped_subscription';

    private bool $dryRun = true;

    private DateTime $pauseAt;

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly SubscriptionPausesRepository $subscriptionPausesRepository,
        private readonly SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
        private readonly DatabaseTransaction $databaseTransaction,
        private readonly DataProviderManager $dataProviderManager,
        private readonly ApplicationConfig $applicationConfig,
        private readonly Emitter $emitter,
        private readonly SubscriptionMetaRepository $subscriptionMetaRepository,
    ) {
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    public function reset(): void
    {
        $this->dryRun = true;
    }

    /**
     * @throws Throwable
     */
    public function pauseSubscription(ActiveRow $subscription, DateTime $pauseAt, DateTime $resumeAt): array
    {
        if ($subscription->start_time > $pauseAt || $subscription->end_time <= $pauseAt) {
            throw new \RuntimeException('Subscription is not active, cannot pause.');
        }

        if ($this->subscriptionPausesRepository->hasScheduledOrActivePause($subscription)) {
            throw new \RuntimeException('Subscription is already paused with a future resume date.');
        }

        $this->pauseAt = $pauseAt;

        $this->databaseTransaction->start();

        $subscriptionPauseResult = [
            'dry_run' => $this->dryRun,
        ];
        try {
            // split active subscription
            [$subscriptionPauseResult[self::ACTIVE_SUBSCRIPTION_CHANGES], $newSubscriptionIds] = $this->splitSubscription($subscription, $resumeAt);

            // move future subscriptions and active recurrent payments
            $subscriptionPauseResult[self::FUTURE_SUBSCRIPTION_CHANGES] = $this->moveUsersFutureSubscriptions($subscription->user, $resumeAt, $newSubscriptionIds);
            $subscriptionPauseResult[self::RECURRENT_PAYMENTS_CHANGES] = $this->moveActiveRecurrentPayments($subscription->user, $resumeAt);

            if (!$this->dryRun) {
                $this->subscriptionPausesRepository->add(
                    $subscription,
                    $this->pauseAt,
                    $resumeAt,
                    $this->cleanUpSubscriptionPauseResultChanges($subscriptionPauseResult),
                    $newSubscriptionIds[0],
                );
                $this->databaseTransaction->commit();
            } else {
                $this->databaseTransaction->rollback();
            }
        } catch (\Throwable $e) {
            $this->databaseTransaction->rollback();
            throw $e;
        }

        return $subscriptionPauseResult;
    }

    public function isSubscriptionPausable(ActiveRow $subscription): bool
    {
        $config = $this->applicationConfig->get('allow_pause_subscriptions');
        if (!$config) {
            return false;
        }

        $now = $this->getNow();

        // already stopped
        if ($subscription->start_time == $subscription->end_time) {
            return false;
        }

        if ($subscription->end_time <= $now || $subscription->start_time > $now) {
            return false;
        }

        if ($this->subscriptionPausesRepository->hasScheduledOrActivePause($subscription)) {
            return false;
        }

        /** @var IsSubscriptionPausableDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders(
            'payments.dataprovider.pause_subscription_widget.is_pausable',
            IsSubscriptionPausableDataProviderInterface::class,
        );
        foreach ($providers as $sorting => $provider) {
            $isPausable = $provider->isSubscriptionPausable($subscription);
            if (!$isPausable) {
                return false;
            }
        }

        if ($this->subscriptionsRepository->actualUserSubscriptions($subscription->user_id)->count('*') > 1) {
            // user has more than one active subscription, so we don't allow pausing
            return false;
        }

        return true;
    }

    public function stopResumedSubscriptions(ActiveRow $pausedSubscription): void
    {
        // No dry run here, the refund already happened.
        $this->databaseTransaction->wrap(function () use ($pausedSubscription) {
            $pauses = $this->subscriptionPausesRepository->subscriptionPauses($pausedSubscription);
            foreach ($pauses as $pause) {
                if ($pause->resumed_subscription_id === null) {
                    Debugger::log(
                        "Subscription pause #{$pause->id} has no resumed subscription linked, cannot stop it.",
                        Debugger::ERROR,
                    );
                    continue;
                }

                // $pausedSubscription may be stale (loaded before the pause), so reload it from the pause row.
                $resumedSubscription = $pause->ref('subscriptions', 'resumed_subscription_id');
                $this->stopResumedSubscription($pause->ref('subscriptions', 'subscription_id'), $resumedSubscription);
                $this->stopResumedSubscriptions($resumedSubscription);
            }
        });
    }

    private function stopResumedSubscription(ActiveRow $pausedSubscription, ActiveRow $resumedSubscription): void
    {
        $now = $this->getNow();
        $alreadyStopped = $resumedSubscription->end_time <= $resumedSubscription->start_time;
        $alreadyEnded = $resumedSubscription->end_time <= $now;
        if ($alreadyStopped || $alreadyEnded) {
            return;
        }

        $originalEndTime = clone $resumedSubscription->end_time;
        $note = $resumedSubscription->note . "\n[pause] Subscription stopped with paused subscription #{$pausedSubscription->id}. "
            . "Original start_time {$resumedSubscription->start_time->format(DATE_RFC3339)}, end_time {$originalEndTime->format(DATE_RFC3339)}";

        if ($resumedSubscription->start_time > $now) {
            // Not started yet, so end it where the paused subscription ends and a new purchase does not
            // wait for it. Not before now, SubscriptionShortenedHandler moves following subscriptions to this end.
            $stopAt = max($pausedSubscription->end_time, $now);
            $this->subscriptionEndsSuppressionManager->suppressNotifications($resumedSubscription);
            $this->subscriptionsRepository->update($resumedSubscription, [
                'start_time' => $stopAt,
                'end_time' => $stopAt,
                'note' => $note,
            ]);
        } else {
            $this->subscriptionsRepository->update($resumedSubscription, [
                'end_time' => $now,
                'note' => $note,
            ]);
        }

        $this->subscriptionMetaRepository->setMeta(
            $resumedSubscription,
            StopSubscriptionHandler::META_KEY_EXPIRED_BY_ADMIN,
            true,
        );

        $this->emitter->emit(new SubscriptionShortenedEvent($resumedSubscription, $originalEndTime));
    }

    private function moveActiveRecurrentPayments(ActiveRow $user, DateTime $resumeAt): array
    {
        $recurrentPaymentsMovedResult = [];
        $activeRecurrentPayments = $this->recurrentPaymentsRepository->getUserActiveRecurrentPayments($user);

        $moveBy = $this->pauseAt->diff($resumeAt);

        foreach ($activeRecurrentPayments as $recurrentPayment) {
            $originalChargeAt = clone $recurrentPayment->charge_at;
            $newChargeAt = (clone $recurrentPayment->charge_at)->add($moveBy);

            if (!$this->dryRun) {
                $this->recurrentPaymentsRepository->update($recurrentPayment, [
                    'charge_at' => $newChargeAt,
                    'note' => "[pause] Recurrent payment moved original charge_at {$originalChargeAt->format(DATE_RFC3339)}",
                ]);
            }
            $recurrentPaymentsMovedResult[] = [
                'id' => $recurrentPayment->id,
                'row' => $recurrentPayment,
                'new_charge_at' => $newChargeAt,
                'original_charge_at' => $originalChargeAt,
            ];
        }

        return $recurrentPaymentsMovedResult;
    }

    private function moveUsersFutureSubscriptions(ActiveRow $user, DateTime $resumeAt, array $newSubscriptionIds): array
    {
        $subscriptionsMoveResult = [];
        $subscriptions = $this->subscriptionsRepository->all()
            ->where([
                'subscriptions.user_id' => $user->id,
                'start_time > ?' => $this->pauseAt,
            ])
            ->order('subscriptions.end_time ASC, subscriptions.start_time ASC');
        if (!empty($newSubscriptionIds)) {
            $subscriptions->where('`id` NOT IN (?)', $newSubscriptionIds);
        }

        $moveBy = $this->pauseAt->diff($resumeAt);

        foreach ($subscriptions as $subscription) {
            $originalStartTime = clone $subscription->start_time;
            $originalEndTime = clone $subscription->end_time;
            $newStartTime = (clone $subscription->start_time)->add($moveBy);
            $newEndTime = (clone $subscription->end_time)->add($moveBy);

            if (!$this->dryRun) {
                $this->subscriptionsRepository->moveSubscription($subscription, $newStartTime);
                $this->subscriptionsRepository->update($subscription, [
                    'note' => "[pause] Subscription moved. Original start_time {$subscription->start_time->format(DATE_RFC3339)}",
                ]);
            }

            $subscriptionsMoveResult[] = [
                'id' => $subscription->id,
                'row' => $subscription,
                'new_start_time' => $newStartTime,
                'original_start_time' => $originalStartTime,
                'new_end_time' => $newEndTime,
                'original_end_time' => $originalEndTime,
            ];
        }

        return $subscriptionsMoveResult;
    }

    private function splitSubscription(ActiveRow $subscription, DateTime $resumeAt): array
    {
        $subscriptionSplitResult = [];

        $payment = $subscription->related('payments')->fetch();

        $originalEndTime = clone $subscription->end_time;
        $originalStartTime = clone $subscription->start_time;
        $restOfSubscriptionLength = $this->pauseAt->diff($originalEndTime);

        // Calculate the new end time based on the remaining time of the subscription
        $newSubscriptionStartTime = $resumeAt;
        $newSubscriptionEndTime = (clone $newSubscriptionStartTime)->add($restOfSubscriptionLength);

        $newSubscriptionIds = [];

        // Create a new subscription with the updated start and end times
        $newSubscription = null;
        if (!$this->dryRun) {
            $newSubscription = $this->subscriptionsRepository->add(
                $subscription->subscription_type,
                $payment && (bool)$payment->payment_gateway->is_recurrent,
                true,
                $subscription->user,
                $subscription->type,
                $newSubscriptionStartTime,
                $newSubscriptionEndTime,
                "[pause] Subscription splitted from #{$subscription->id}",
                $subscription->address,
                false,
            );

            $newSubscriptionIds[] = $newSubscription->id;
        }

        $subscriptionSplitResult[self::NEW_SUBSCRIPTION] = [
            'id' => $newSubscription?->id,
            'start_time' => $newSubscriptionStartTime,
            'end_time' => $newSubscriptionEndTime,
        ];

        // Stop the original subscription
        if (!$this->dryRun) {
            $this->subscriptionEndsSuppressionManager->suppressNotifications($subscription);
            $this->subscriptionsRepository->update($subscription, [
                'end_time' => $this->pauseAt,
                'note' => "[pause] Subscription stopped original end_time " . $subscription->end_time->format(DATE_RFC3339),
            ]);
        }

        $subscriptionSplitResult[self::STOPPED_SUBSCRIPTION] = [
            'id' => $subscription->id,
            'row' => $subscription,
            'original_end_time' => $originalEndTime,
            'original_start_time' => $originalStartTime,
        ];

        return [$subscriptionSplitResult, $newSubscriptionIds];
    }

    private function cleanUpSubscriptionPauseResultChanges(array $subscriptionPauseResult): array
    {
        unset($subscriptionPauseResult['dry_run']);
        unset($subscriptionPauseResult[self::ACTIVE_SUBSCRIPTION_CHANGES][self::STOPPED_SUBSCRIPTION]['row']);

        foreach ($subscriptionPauseResult[self::FUTURE_SUBSCRIPTION_CHANGES] as &$futureSubscriptionChange) {
            unset($futureSubscriptionChange['row']);
        }

        foreach ($subscriptionPauseResult[self::RECURRENT_PAYMENTS_CHANGES] as &$recurrentPaymentsChange) {
            unset($recurrentPaymentsChange['row']);
        }

        return $subscriptionPauseResult;
    }
}
