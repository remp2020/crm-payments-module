<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Components\SimpleWidgetFactoryInterface;
use Crm\ApplicationModule\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Widget\LazyWidgetManager;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;

/**
 * Listing widget used in user detail shoing users payments.
 *
 * This widget fetches all user payments. Renders bootstrap table with resulting dataset
 * and adds change payment status widget and abilit to add any number of simple widgets.
 * Also handles stopping recurrent payment.
 *
 * @package Crm\PaymentsModule\Components
 */
class UserPaymentsListing extends BaseLazyWidget
{
    private $templateName = 'user_payments_listing.latte';

    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $parsedMailLogsRepository;

    private $translator;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        Translator $translator,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ParsedMailLogsRepository $parsedMailLogsRepository
    ) {
        parent::__construct($lazyWidgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->parsedMailLogsRepository = $parsedMailLogsRepository;
        $this->translator = $translator;
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('payments.admin.component.user_payments_listing.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        $todayPayments = $this->paymentsRepository->userPayments($id)->where([
            'status' => PaymentsRepository::STATUS_PAID,
            'paid_at > ?' => DateTime::from(strtotime('today 00:00')),
        ])->count('*');
        if ($todayPayments) {
            $header .= ' <span class="label label-warning">' . $this->translator->translate('payments.admin.component.user_payments_listing.today') . '</span>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'userpayments';
    }

    public function render($id)
    {
        $this->template->userId = $id;

        $payments = $this->paymentsRepository->userPayments($id);
        $variableSymbols = [];
        foreach ($payments as $payment) {
            $variableSymbols[] = $payment->variable_symbol;
        }
        $this->template->payments = $payments;
        $this->template->paymentStatuses = $this->paymentsRepository->getStatusPairs();
        $this->template->totalPayments = $this->totalCount($id);
        $this->template->parsedEmails = $this->parsedMailLogsRepository->findByVariableSymbols($variableSymbols);

        $recurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($id)
            ->order('id DESC, charge_at DESC');
        $this->template->recurrentPayments = $recurrentPayments;
        $this->template->totalRecurrentPayments = $recurrentPayments->count('*');
        $this->template->canBeStopped = function ($recurrentPayment) {
            return $this->recurrentPaymentsRepository->canBeStopped($recurrentPayment);
        };

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleStopRecurrentPayment($recurrentPaymentId)
    {
        $recurrent = $this->recurrentPaymentsRepository->stoppedByAdmin($recurrentPaymentId);

        if (!$recurrent) {
            throw new BadRequestException();
        }
        $user = $recurrent->user;
        $this->presenter->redirect(':Users:UsersAdmin:Show', $user->id);
    }

    private $totalCount = null;

    private function totalCount($id)
    {
        if ($this->totalCount == null) {
            $this->totalCount = $this->paymentsRepository->userPayments($id)->count('*');
        }
        return $this->totalCount;
    }

    protected function createComponentChangePaymentStatus(ChangePaymentStatusFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }

    protected function createComponentSimpleWidget(SimpleWidgetFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }
}
