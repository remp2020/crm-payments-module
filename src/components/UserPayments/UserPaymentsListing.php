<?php

namespace Crm\PaymentsModule\Components;

use Crm\ApplicationModule\Widget\BaseWidget;
use Crm\ApplicationModule\Widget\WidgetManager;
use Crm\PaymentsModule\MailConfirmation\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Utils\DateTime;

class UserPaymentsListing extends BaseWidget
{
    private $templateName = 'user_payments_listing.latte';

    /** @var PaymentsRepository */
    private $paymentsRepository;

    /** @var RecurrentPaymentsRepository */
    private $recurrentPaymentsRepository;

    /** @var ParsedMailLogsRepository  */
    private $parsedMailLogsRepository;

    public function __construct(
        WidgetManager $widgetManager,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        ParsedMailLogsRepository $parsedMailLogsRepository
    ) {
        parent::__construct($widgetManager);
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->parsedMailLogsRepository = $parsedMailLogsRepository;
    }

    public function header($id = '')
    {
        $header = 'Platby';
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        $todayPayments = $this->paymentsRepository->userPayments($id)->where([
            'status' => PaymentsRepository::STATUS_PAID,
            'paid_at > ?' => DateTime::from(strtotime('today 00:00')),
        ])->count('*');
        if ($todayPayments) {
            $header .= ' <span class="label label-warning">Dnes</span>';
        }
        return $header;
    }

    public function identifier()
    {
        return 'userpayments';
    }

    public function render($id)
    {
        $this->template->addFilter('recurrentStatus', function ($status) {
            $data = ComfortPayStatus::getStatusHtml($status);
            return '<span class="label label-' . $data['label'] . '">' . $data['text'] . '</span>';
        });

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

        $recurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($id);
        $this->template->recurrentPayments = $recurrentPayments;
        $this->template->totalRecurrentPayments = $recurrentPayments->count('*');

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
    protected function createComponentGiftCoupons(GiftCouponsFactoryInterface $factory)
    {
        $control = $factory->create();
        return $control;
    }
}