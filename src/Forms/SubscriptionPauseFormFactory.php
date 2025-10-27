<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Models\Subscription\SubscriptionPauser;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class SubscriptionPauseFormFactory
{
    public function __construct(
        private readonly SubscriptionsRepository $subscriptionsRepository,
        private readonly SubscriptionPauser $subscriptionPauser,
        private readonly Translator $translator,
    ) {
    }

    public function create(int $subscriptionId, string $pauseAt, string $resumeAt): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapInlineRenderer);
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addHidden('subscription_id', $subscriptionId)
            ->addRule(Form::Integer);

        $form->addHidden('resume_at')
            ->setRequired('payments.admin.subscriptions_pause.form.resume_at.required');

        $form->addHidden('pause_at')
            ->setRequired('payments.admin.subscriptions_pause.form.pause_at.required');

        $form->addSubmit('send', 'payments.admin.subscriptions_pause.form.pause_subscription_button')
            ->setHtmlAttribute('class', 'btn btn-danger btn-lg button');

        $form->setDefaults([
            'resume_at' => $resumeAt ? (new \DateTime($resumeAt))->format(DATE_RFC3339) : null,
            'pause_at' => $pauseAt ? (new \DateTime($pauseAt))->format(DATE_RFC3339) : null,
            'subscription_id' => $subscriptionId,
        ]);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded(Form $form, $values)
    {
        $subscriptionId = $values->subscription_id;
        $pauseAt = new \DateTime($values->pause_at);
        $resumeAt = new \DateTime($values->resume_at);

        $subscription = $this->subscriptionsRepository->find($subscriptionId);
        if (!$subscription) {
            throw new \RuntimeException('Subscription not found');
        }

        $this->subscriptionPauser->setDryRun(false);

        try {
            $this->subscriptionPauser->pauseSubscription(
                $subscription,
                $pauseAt,
                $resumeAt,
            );
        } catch (\Throwable $e) {
            $form->addError($e->getMessage());
            return;
        }
    }
}
