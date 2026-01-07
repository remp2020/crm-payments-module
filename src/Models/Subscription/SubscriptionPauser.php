<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Models\Subscription;

use Crm\ApplicationModule\Database\DatabaseTransaction;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\PaymentsModule\Repositories\SubscriptionPausesRepository;
use Crm\SubscriptionsModule\Models\Subscription\SubscriptionEndsSuppressionManager;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use DateTime;
use Nette\Database\Table\ActiveRow;

class SubscriptionPauser
{
    const ACTIVE_SUBSCRIPTION_CHANGES = 'active_subscription_changes';
    const FUTURE_SUBSCRIPTION_CHANGES = 'future_subscription_changes';
    const RECURRENT_PAYMENTS_CHANGES = 'recurrent_payments_changes';

    const NEW_SUBSCRIPTION = 'new_subscription';
    const STOPPED_SUBSCRIPTION = 'stopped_subscription';

    private bool $dryRun = true;

    private DateTime $pauseAt;

    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly SubscriptionPausesRepository $subscriptionPausesRepository,
        private readonly SubscriptionEndsSuppressionManager $subscriptionEndsSuppressionManager,
        private readonly DatabaseTransaction $databaseTransaction,
    ) {
    }

    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * @throws \Throwable
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
