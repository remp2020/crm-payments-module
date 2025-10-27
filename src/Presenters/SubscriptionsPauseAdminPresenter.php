<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Forms\SubscriptionPauseFormFactory;
use Crm\PaymentsModule\Forms\SubscriptionPausePreviewFormFactory;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\DI\Attributes\Inject;

class SubscriptionsPauseAdminPresenter extends AdminPresenter
{
    #[Inject]
    public SubscriptionsRepository $subscriptionsRepository;

    #[Inject]
    public SubscriptionPauser $subscriptionPauser;

    #[Inject]
    public SubscriptionPausePreviewFormFactory $subscriptionPausePreviewFormFactory;

    #[Inject]
    public SubscriptionPauseFormFactory $subscriptionPauseFormFactory;

    /**
     * @admin-access-level write
     */
    public function renderDefault(int $id)
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            $this->error("Subscription ID: {$id} not found");
        }

        $this->template->subscription = $subscription;
    }

    /**
     * @admin-access-level write
     */
    public function renderPreviewChanges(int $id, string $pauseAt, string $resumeAt)
    {
        $subscription = $this->subscriptionsRepository->find($id);
        if (!$subscription) {
            $this->error("Subscription ID: {$id} not found");
        }

        $this->subscriptionPauser->setDryRun(true);

        $previewChangesResult = $this->subscriptionPauser->pauseSubscription(
            $subscription,
            new \DateTime($pauseAt),
            new \DateTime($resumeAt),
        );

        $currentSubscriptions = $this->subscriptionsRepository->all()
            ->where('user_id', $subscription->user_id)
            ->where('end_time > ?', new \DateTime())
            ->order('subscriptions.end_time ASC, subscriptions.start_time ASC')
            ->fetchAll();

        $previewEndDate = $previewChangesResult[SubscriptionPauser::ACTIVE_SUBSCRIPTION_CHANGES]['new_subscription']['end_time'];
        foreach ($previewChangesResult[SubscriptionPauser::FUTURE_SUBSCRIPTION_CHANGES] as $futureChange) {
            if ($futureChange['new_end_time'] > $previewEndDate) {
                $previewEndDate = $futureChange['new_end_time'];
            }
        }
        $daysRange = $previewEndDate->diff($subscription->start_time)->days;

        $this->template->currentSubscriptions = $currentSubscriptions;
        $this->template->previewChangesResult = $previewChangesResult;
        $this->template->pauseAt = new \DateTime($pauseAt);
        $this->template->resumeAt = new \DateTime($resumeAt);
        $this->template->subscription = $subscription;
        $this->template->daysRange = $daysRange;
    }

    public function createComponentSubscriptionPausePreviewForm()
    {
        $subscriptionId = $this->getParameter('id');
        $form = $this->subscriptionPausePreviewFormFactory->create(intval($subscriptionId));

        $form->onSuccess[] = function (Form $form, $values) use ($subscriptionId) {
            $this->redirect('previewChanges', [
                'id' => $subscriptionId,
                'pauseAt' => $values->pause_at,
                'resumeAt' => $form->getValues()->resume_at->format(DATE_RFC3339),
            ]);
        };

        return $form;
    }

    public function createComponentSubscriptionPauseForm()
    {
        $subscriptionId = $this->getParameter('id');
        $pauseAt = $this->getParameter('pauseAt');
        $resumeAt = $this->getParameter('resumeAt');

        $form = $this->subscriptionPauseFormFactory->create(intval($subscriptionId), $pauseAt, $resumeAt);

        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        $form->onError[] = function () use ($subscription) {
            $this->flashMessage(
                $this->translator->translate(
                    'payments.admin.subscriptions_pause.form.pause_subscription_error',
                ),
                'warning',
            );
            $this->redirect(':Users:UsersAdmin:show', [
                'id' => $subscription->user_id,
            ]);
        };

        $form->onSuccess[] = function () use ($subscription) {
            $this->flashMessage(
                $this->translator->translate(
                    'payments.admin.subscriptions_pause.form.pause_complete_message',
                ),
            );
            $this->redirect(':Users:UsersAdmin:show', [
                'id' => $subscription->user_id,
            ]);
        };

        return $form;
    }
}
