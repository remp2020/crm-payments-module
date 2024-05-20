<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Components\PreviousNextPaginator\PreviousNextPaginator;
use Crm\ApplicationModule\Models\Database\Selection;
use Crm\PaymentsModule\Forms\ParsedMailLogFactory;
use Crm\PaymentsModule\Models\ParsedMailLog\State;
use Crm\PaymentsModule\Repositories\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Exception;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use Nette\DI\Attributes\Inject;
use Tomaj\Form\Renderer\BootstrapRenderer;

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

    #[Persistent]
    public $amountFrom;

    #[Persistent]
    public $amountTo;

    public function __construct(
        private readonly ParsedMailLogFactory $parsedMailLogFactory,
    ) {
        parent::__construct();
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault()
    {
        $logs = $this->getFilteredLogsSelection();

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
        $form->setRenderer(new BootstrapRenderer());
        $form->setTranslator($this->translator);

        $form->addGroup('main')->setOption('label', null); // main group

        $collapseGroup = $form->addGroup('collapse', false)
            ->setOption('container', 'div class="collapse"')
            ->setOption('label', null)
            ->setOption('id', 'filterFormCollapsableGroup');
        $buttonGroup = $form->addGroup('button', false)->setOption('label', null);

        $form->addText('vs', 'payments.admin.parsed_mails.variable_symbol.label')
            ->setHtmlAttribute('autofocus');

        $form->addSelect('state', 'payments.admin.parsed_mails.state.label', State::getFriendlyList())
            ->setPrompt('--');

        $form->addSelect(
            'payment_status',
            'payments.admin.parsed_mails.payment_status.label',
            $this->paymentsRepository->getStatusPairs(),
        )->setPrompt('--');

        $form->setCurrentGroup($collapseGroup);
        $form->addText('amount_from', 'payments.admin.parsed_mails_filter_form.amount_from.label')
            ->setHtmlAttribute('type', 'number');

        $form->addText('amount_to', 'payments.admin.parsed_mails_filter_form.amount_to.label')
            ->setHtmlAttribute('type', 'number');

        $form->setCurrentGroup($buttonGroup);

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

        $form->addButton('more')
            ->setHtmlAttribute('data-toggle', 'collapse')
            ->setHtmlAttribute('data-target', '#filterFormCollapsableGroup')
            ->setHtmlAttribute('class', 'btn btn-info')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fas fa-caret-down"></i> ' . $this->translator->translate('payments.admin.parsed_mails_filter_form.filter.more'));

        $form->onSuccess[] = [$this, 'adminFilterSubmited'];
        $form->setDefaults([
            'state' => $this->state,
            'payment_status' => $this->paymentStatus,
            'vs' => $this->vs,
            'amount_from' => $this->amountFrom,
            'amount_to' => $this->amountTo,
        ]);
        return $form;
    }

    public function adminFilterSubmited($form, $values)
    {
        $this->redirect('default', [
            'state' => $values['state'],
            'paymentStatus' => $values['payment_status'],
            'vs' => $values['vs'],
            'amountFrom' => $values['amount_from'],
            'amountTo' => $values['amount_to'],
        ]);
    }

    protected function createComponentEditForm(): Multiplier
    {
        return new Multiplier(function (string $parsedMailLogId) {
            $parsedMailLog = $this->parsedMailLogsRepository->find((int) $parsedMailLogId);
            if (!$parsedMailLog) {
                throw new Exception('Parsed mail log not found.');
            }

            $form = $this->parsedMailLogFactory->create($parsedMailLog->id, [
                'state' => $parsedMailLog->state,
                'note' => $parsedMailLog->note,
            ]);

            $form->onSuccess[] = function (Form $form, array $values) use ($parsedMailLog): void {
                $this->parsedMailLogsRepository->update($parsedMailLog, [
                    'state' => $values['state'],
                    'note' => $values['note'],
                ]);

                $this->flashMessage($this->translator->translate('payments.admin.parsed_mails_edit_form.success_message'));
                $this->redirect('default');
            };

            return $form;
        });
    }

    private function getFilteredLogsSelection(): Selection
    {
        $logs = $this->parsedMailLogsRepository->all($this->vs, $this->state, $this->paymentStatus);

        if ($this->amountFrom) {
            $logs->where('amount >= ?', $this->amountFrom);
        }

        if ($this->amountTo) {
            $logs->where('amount <= ?', $this->amountTo);
        }

        return $logs;
    }
}
