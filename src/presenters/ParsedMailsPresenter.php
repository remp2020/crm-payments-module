<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Components\VisualPaginator;
use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Nette\Application\UI\Form;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class ParsedMailsPresenter extends AdminPresenter
{
    /** @var ParsedMailLogsRepository @inject */
    public $parsedMailLogsRepository;

    /** @persistent */
    public $vs;

    /** @persistent */
    public $state;

    public function renderDefault()
    {
        $logs = $this->parsedMailLogsRepository->all($this->vs, $this->state);

        $vp = new VisualPaginator();
        $this->addComponent($vp, 'vp');
        $paginator = $vp->getPaginator();
        $paginator->setItemCount($logs->count('*'));
        $paginator->setItemsPerPage($this->onPage);
        $this->template->vp = $vp;

        $this->template->logs = $logs->limit($paginator->getLength(), $paginator->getOffset());

        $this->template->logs_sum = $this->parsedMailLogsRepository->totalCount();
    }

    public function createComponentFilterForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('vs', 'payments.admin.parsed_mails.variable_symbol.label')
            ->setAttribute('autofocus');

        $states = [
            ParsedMailLogsRepository::STATE_WITHOUT_VS => ParsedMailLogsRepository::STATE_WITHOUT_VS,
            ParsedMailLogsRepository::STATE_ALREADY_PAID => ParsedMailLogsRepository::STATE_ALREADY_PAID,
            ParsedMailLogsRepository::STATE_CHANGED_TO_PAID => ParsedMailLogsRepository::STATE_CHANGED_TO_PAID,
            ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND => ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND,
            ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT => ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT,
            ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT => ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT,
        ];
        $form->addselect('state', 'payments.admin.parsed_mails.state.label', $states)
            ->setPrompt('--');

        $form->addSubmit('send', 'payments.admin.parsed_mails.filter')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.parsed_mails.filter'));

        $form->addSubmit('cancel', 'payments.admin.parsed_mails.cancel')->onClick[] = function () {
            $this->redirect('default', ['state' => '', 'vs' => '']);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'state' => $this->state,
            'vs' => $this->vs,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('default', [
            'state' => $values['state'],
            'vs' => $values['vs'],
        ]);
    }
}
