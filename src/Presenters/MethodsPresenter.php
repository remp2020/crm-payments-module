<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\AuthorizationInterface;
use Crm\PaymentsModule\Models\PaymentItem\AuthorizationPaymentItem;
use Crm\PaymentsModule\Models\PaymentItem\PaymentItemContainer;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentGatewaysRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Crm\UsersModule\Models\Auth\UserManager;
use Nette\Application\BadRequestException;

class MethodsPresenter extends FrontendPresenter
{
    private PaymentGatewaysRepository $paymentGatewaysRepository;

    private UserManager $userManager;

    private PaymentProcessor $paymentProcessor;

    private GatewayFactory $gatewayFactory;

    private PaymentsRepository $paymentsRepository;

    private RecurrentPaymentsRepository $recurrentPaymentsRepository;

    public function __construct(
        PaymentGatewaysRepository $paymentGatewaysRepository,
        UserManager $userManager,
        PaymentProcessor $paymentProcessor,
        GatewayFactory $gatewayFactory,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository
    ) {
        parent::__construct();

        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->userManager = $userManager;
        $this->paymentProcessor = $paymentProcessor;
        $this->gatewayFactory = $gatewayFactory;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function renderAdd(string $paymentGatewayCode, int $recurrentPaymentId = null)
    {
        $this->onlyLoggedIn();

        $paymentGateway = $this->paymentGatewaysRepository->findByCode($paymentGatewayCode);
        if (!$paymentGateway) {
            throw new BadRequestException('Payment with code not found: ' . $paymentGatewayCode);
        }

        $gateway = $this->gatewayFactory->getGateway($paymentGateway->code);
        if (!$gateway instanceof AuthorizationInterface) {
            throw new BadRequestException("Payment gateway: {$paymentGateway->code} doesn't support authorization payment.");
        }

        $userRow = $this->userManager->loadUser($this->getUser());
        if ($recurrentPaymentId !== null) {
            $recurrentPaymentRow = $this->recurrentPaymentsRepository->getUserActiveRecurrentPayments($userRow->id)
                ->where('id', $recurrentPaymentId)
                ->fetch();

            if (!$recurrentPaymentRow) {
                throw new BadRequestException("Active recurrent payment with id not found: {$recurrentPaymentId} for user: {$userRow->id}");
            }
        }

        $paymentItemContainer = (new PaymentItemContainer())->addItem(
            new AuthorizationPaymentItem('authorization', $gateway->getAuthorizationAmount())
        );

        $payment = $this->paymentsRepository->add(
            null,
            $paymentGateway,
            $userRow,
            $paymentItemContainer,
            $this->getReferer()
        );

        if ($recurrentPaymentId !== null) {
            $this->paymentsRepository->addMeta($payment, [
                'recurrent_payment_id_to_update_cid' => $recurrentPaymentId
            ]);
        }

        $this->paymentProcessor->begin($payment, true);
    }

    public function renderComplete(int $paymentId)
    {
        $paymentRow = $this->paymentsRepository->find($paymentId);
        if ($paymentRow->status === PaymentsRepository::STATUS_AUTHORIZED) {
            $this->flashMessage($this->translator->translate('payments.frontend.add_card.success'));
            $this->redirect('Payments:my');
        }

        $this->flashMessage($this->translator->translate('payments.frontend.add_card.error'), 'error');
        $this->redirect('Payments:my');
    }
}
