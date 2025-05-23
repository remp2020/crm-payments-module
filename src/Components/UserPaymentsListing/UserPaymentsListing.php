<?php

namespace Crm\PaymentsModule\Components\UserPaymentsListing;

use Crm\ApplicationModule\Components\Widgets\SimpleWidget\SimpleWidgetFactoryInterface;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Components\ChangePaymentStatus\ChangePaymentStatusFactoryInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
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

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private Translator $translator,
        private PaymentsRepository $paymentsRepository,
        private RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private ParsedMailLogsRepository $parsedMailLogsRepository,
        private RecurrentPaymentsResolver $recurrentPaymentsResolver,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header($id = '')
    {
        $header = $this->translator->translate('payments.admin.component.user_payments_listing.header');
        if ($id) {
            $header .= ' <small>(' . $this->totalCount($id) . ')</small>';
        }
        $todayPayments = $this->paymentsRepository->userPayments($id)->where([
            'status' => PaymentStatusEnum::Paid->value,
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
        $this->template->nextSubscriptionTypeResolver = function ($recurrentPayment) {
            return $this->recurrentPaymentsResolver->resolveSubscriptionType($recurrentPayment);
        };

        $this->template->resolveChargeAmount = function (ActiveRow $recurrentPayment): ?float {
            if ($recurrentPayment->state !== RecurrentPaymentStateEnum::Active->value) {
                return null;
            }

            return $this->recurrentPaymentsResolver->resolveChargeAmount($recurrentPayment);
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
