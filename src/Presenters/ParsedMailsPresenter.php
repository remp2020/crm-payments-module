<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\DI\Attributes\Inject;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class ParsedMailsPresenter extends AdminPresenter
{
    #[Inject]
    public ParsedMailLogsRepository $parsedMailLogsRepository;

    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Persistent]
    public $vs;

    #[Persistent]
    public $state;

    #[Persistent]
    public $paymentStatus;

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $logs = $this->parsedMailLogsRepository->all($this->vs, $this->state, $this->paymentStatus);

        $pnp = new PreviousNextPaginator();
        $this->addComponent($pnp, 'paginator');
        $paginator = $pnp->getPaginator();
        $paginator->setItemsPerPage($this->onPage);

        $logs = $logs->limit($paginator->getLength(), $paginator->getOffset())->fetchAll();
        $pnp->setActualItemCount(count($logs));

        $this->template->logs = $logs;
    }

    public function createComponentFilterForm()
    {
        $form = new Form();
        $form->setRenderer(new BootstrapInlineRenderer());
        $form->setTranslator($this->translator);

        $form->addText('vs', 'payments.admin.parsed_mails.variable_symbol.label')
            ->setHtmlAttribute('autofocus');

        $states = [
            ParsedMailLogsRepository::STATE_WITHOUT_VS => ParsedMailLogsRepository::STATE_WITHOUT_VS,
            ParsedMailLogsRepository::STATE_ALREADY_PAID => ParsedMailLogsRepository::STATE_ALREADY_PAID,
            ParsedMailLogsRepository::STATE_CHANGED_TO_PAID => ParsedMailLogsRepository::STATE_CHANGED_TO_PAID,
            ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND => ParsedMailLogsRepository::STATE_PAYMENT_NOT_FOUND,
            ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT => ParsedMailLogsRepository::STATE_DIFFERENT_AMOUNT,
            ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT => ParsedMailLogsRepository::STATE_AUTO_NEW_PAYMENT,
            ParsedMailLogsRepository::STATE_DUPLICATED_PAYMENT => ParsedMailLogsRepository::STATE_DUPLICATED_PAYMENT,
            ParsedMailLogsRepository::STATE_ALREADY_REFUNDED => ParsedMailLogsRepository::STATE_ALREADY_REFUNDED,
        ];
        $form->addSelect('state', 'payments.admin.parsed_mails.state.label', $states)
            ->setPrompt('--');

        $form->addSelect(
            'payment_status',
            'payments.admin.parsed_mails.payment_status.label',
            $this->paymentsRepository->getStatusPairs(),
        )->setPrompt('--');

        $form->addSubmit('send', 'payments.admin.parsed_mails.filter')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-filter"></i> ' . $this->translator->translate('payments.admin.parsed_mails.filter'));

        $form->addSubmit('cancel', 'payments.admin.parsed_mails.cancel')->onClick[] = function () {
            $this->redirect('default', [
                'state' => null,
                'vs' => null,
                'paymentStatus' => null,
            ]);
        };

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'state' => $this->state,
            'payment_status' => $this->paymentStatus,
            'vs' => $this->vs,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('default', [
            'state' => $values['state'],
            'paymentStatus' => $values['payment_status'],
            'vs' => $values['vs'],
        ]);
    }
}
