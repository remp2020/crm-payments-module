<?php
declare(strict_types=1);

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class SubscriptionPausePreviewFormFactory
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function create(int $subscriptionId): Form
    {
        $form = new Form;
        $form->setRenderer(new BootstrapRenderer);
        $form->setTranslator($this->translator);
        $form->addProtection();

        $form->addHidden('subscription_id', $subscriptionId)
            ->addRule(Form::Integer);

        $form->addDateTime('resume_at', 'payments.admin.subscriptions_pause.form.resume_at.label')
            ->setRequired('payments.admin.subscriptions_pause.form.resume_at.required')
            ->setHtmlAttribute(
                'placeholder',
                $this->translator->translate('payments.admin.subscriptions_pause.form.resume_at.placeholder'),
            )
            ->setHtmlAttribute('class', 'flatpickr')
            ->setHtmlAttribute('flatpickr_datetime_seconds', "1")
            ->setHtmlAttribute('flatpickr_dateformat', 'Y-m-d H:i:s');

        $form->addHidden('pause_at')
            ->setRequired('payments.admin.subscriptions_pause.form.pause_at.required');

        $form->addSubmit('send', 'payments.admin.subscriptions_pause.form.pause_subscription_button_preview');

        $form->setDefaults([
            'pause_at' => (new \DateTime())->format(DATE_RFC3339),
        ]);

        return $form;
    }
}
