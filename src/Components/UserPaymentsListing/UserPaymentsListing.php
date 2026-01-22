<?php

namespace Crm\PaymentsModule\Components\UserPaymentsListing;

use Crm\ApplicationModule\Components\AjaxDataPaginator\PaginatedComponent;
use Crm\ApplicationModule\Components\AjaxDataPaginator\PaginatesDataTrait;
use Crm\ApplicationModule\Components\Widgets\SimpleWidget\SimpleWidget;
use Crm\ApplicationModule\Components\Widgets\SimpleWidget\SimpleWidgetFactoryInterface;
use Crm\ApplicationModule\Models\Widget\BaseLazyWidget;
use Crm\ApplicationModule\Models\Widget\DetailWidgetInterface;
use Crm\ApplicationModule\Models\Widget\LazyWidgetManager;
use Crm\PaymentsModule\Components\ChangePaymentStatus\ChangePaymentStatus;
use Crm\PaymentsModule\Components\ChangePaymentStatus\ChangePaymentStatusFactoryInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\ParsedMailLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Exception;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Tracy\Debugger;

/**
 * Listing widget used in user detail showing users payments.
 *
 * This widget fetches all user payments. Renders bootstrap table with resulting dataset
 * and adds change payment status widget and ability to add any number of simple widgets.
 * Also handles stopping recurrent payment.
 *
 * @package Crm\PaymentsModule\Components
 */
class UserPaymentsListing extends BaseLazyWidget implements DetailWidgetInterface, PaginatedComponent
{
    use PaginatesDataTrait;

    private string $templateName = 'user_payments_listing.latte';

    /** @var ?int Total payment count for current user */
    private ?int $totalCount = null;

    public function __construct(
        LazyWidgetManager $lazyWidgetManager,
        private readonly Translator $translator,
        private readonly PaymentsRepository $paymentsRepository,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
        private readonly ParsedMailLogsRepository $parsedMailLogsRepository,
        private readonly RecurrentPaymentsResolver $recurrentPaymentsResolver,
    ) {
        parent::__construct($lazyWidgetManager);
    }

    public function header($id = ''): string
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

    public function identifier(): string
    {
        return 'userpayments';
    }

    public function render($id): void
    {
        $this->template->userId = $id;
        $this->entityId = $id;

        $totalPayments = $this->totalCount($id);

        $paymentsTablePaginator = $this->getAjaxPaginator(
            snippetName: 'paymentsTable',
            itemCount: $totalPayments,
        );

        $payments = $this->paymentsRepository
            ->userPayments($id)
            ->limit($paymentsTablePaginator->getLimit(), $paymentsTablePaginator->getOffset());
            
        $variableSymbols = [];
        foreach ($payments as $payment) {
            $variableSymbols[] = $payment->variable_symbol;
        }
        $this->template->payments = $payments;
        $this->template->paymentStatuses = $this->paymentsRepository->getStatusPairs();
        $this->template->totalPayments = $totalPayments;
        $this->template->parsedEmails = $this->parsedMailLogsRepository->findByVariableSymbols($variableSymbols);

        $totalRecurrentPayments = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($id)
            ->count('*');

        $recurrentTablePaginator = $this->getAjaxPaginator(
            snippetName: 'recurrentTable',
            itemCount: $totalRecurrentPayments,
        );

        $recurrentPayments = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($id)
            ->order('charge_at DESC, id DESC')
            ->limit($recurrentTablePaginator->getLimit(), $recurrentTablePaginator->getOffset());
            
        $this->template->recurrentPayments = $recurrentPayments;
        $this->template->totalRecurrentPayments = $totalRecurrentPayments;
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

            try {
                $result = $this->recurrentPaymentsResolver->resolveChargeAmount($recurrentPayment);
            } catch (Exception $exception) {
                Debugger::log($exception, Debugger::EXCEPTION);
                return null;
            }
            return $result;
        };

        $this->template->paymentsTablePaginator = $paymentsTablePaginator;
        $this->template->recurrentTablePaginator = $recurrentTablePaginator;

        $this->template->setFile(__DIR__ . '/' . $this->templateName);
        $this->template->render();
    }

    public function handleStopRecurrentPayment($recurrentPaymentId): void
    {
        $recurrent = $this->recurrentPaymentsRepository->stoppedByAdmin($recurrentPaymentId);

        if (!$recurrent) {
            throw new BadRequestException();
        }
        $user = $recurrent->user;
        $this->presenter->redirect(':Users:UsersAdmin:Show', $user->id);
    }

    private function totalCount(int $userId): int
    {
        return $this->totalCount ??= $this->paymentsRepository
            ->userPayments($userId)
            ->count();
    }

    protected function createComponentChangePaymentStatus(
        ChangePaymentStatusFactoryInterface $factory,
    ): ChangePaymentStatus {
        return $factory->create();
    }

    protected function createComponentSimpleWidget(SimpleWidgetFactoryInterface $factory): SimpleWidget
    {
        return $factory->create();
    }
}
