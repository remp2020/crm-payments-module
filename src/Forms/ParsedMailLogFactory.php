<?php

namespace Crm\PaymentsModule\Forms;

use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Models\ParsedMailLog\ParsedMailLogStateEnum;
use Nette\Localization\Translator;
use Tomaj\Form\Renderer\BootstrapRenderer;

class ParsedMailLogFactory
{
    public function __construct(
        private readonly Translator $translator,
    ) {
    }

    public function create(int $parsedMailLogId, array $defaults): Form
    {
        $form = new Form;
        $form->setTranslator($this->translator);
        $form->setRenderer(new BootstrapRenderer());

        // State
        $form->addSelect('state', 'payments.admin.parsed_mails.state.label', items: ParsedMailLogStateEnum::getFriendlyList());

        // Note
        $form->addTextArea('note', 'payments.admin.parsed_mails.note.label')
            ->setNullable()
            ->setHtmlAttribute('rows', 4);

        // Buttons
        $form->addSubmit('save', 'payments.admin.parsed_mails_edit_form.save');
        $form->addButton('cancel', 'payments.admin.parsed_mails_edit_form.cancel')
            ->setHtmlAttribute('data-toggle', 'modal')
            ->setHtmlAttribute('data-target', sprintf('#parsedMailLogEditModal%d', $parsedMailLogId));

        $form->setDefaults($defaults);

        return $form;
    }
}
