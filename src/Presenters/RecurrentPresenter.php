<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\CannotProcessPayment;
use Crm\PaymentsModule\Models\GatewayFactory;
use Crm\PaymentsModule\Models\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Models\Gateways\ReusableCardPaymentInterface;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Models\RecurrentPaymentsProcessor;
use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectManager;
use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Nette\Utils\DateTime;

class RecurrentPresenter extends FrontendPresenter
{
    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public RecurrentPaymentsRepository $recurrentPaymentsRepository;

    #[Inject]
    public PaymentProcessor $paymentProcessor;

    #[Inject]
    public RecurrentPaymentsProcessor $recurrentPaymentsProcessor;

    #[Inject]
    public GatewayFactory $gatewayFactory;

    #[Inject]
    public PaymentCompleteRedirectManager $paymentCompleteRedirectManager;

    public function renderSelectCard(int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $user = $this->getUser();
        $this->checkPaymentBelongsToUser($user, $payment);
        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);

        $allUserCards = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $payment->ref('payment_gateway')->code])
            ->where(['cid IS NOT NULL AND expires_at > ?' => new DateTime()])
            ->where('state != ?', RecurrentPaymentsRepository::STATE_SYSTEM_STOP)
            ->order('id DESC, charge_at DESC');

        $cardsByExpiration = [];

        foreach ($allUserCards as $card) {
            if (($gateway instanceof ReusableCardPaymentInterface) && !$gateway->isCardReusable($card)) {
                continue;
            }

            $expiration = $card->expires_at->format(DateTime::RFC3339);
            if (!array_key_exists($expiration, $cardsByExpiration) || $cardsByExpiration[$expiration]->created_at < $card->created_at) {
                $cardsByExpiration[$expiration] = $card;
            }
        }

        $this->template->cards = array_values($cardsByExpiration);
        $this->template->payment = $payment;
    }

    public function handleUseNewCard(int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $this->checkPaymentBelongsToUser($this->getUser(), $payment);

        try {
            $this->paymentProcessor->begin($payment);
        } catch (CannotProcessPayment $err) {
            $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
        }
    }

    public function handleUseExistingCard(int $recurrentPaymentId, int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $this->checkPaymentBelongsToUser($this->getUser(), $payment);

        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        if (!$recurrentPayment) {
            $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
        }

        $gateway = $this->gatewayFactory->getGateway($payment->payment_gateway->code);
        if (!$gateway instanceof RecurrentPaymentInterface) {
            throw new \Exception('gateway is not instance of RecurrentPaymentInterface: ' . get_class($gateway));
        }

        $success = $this->recurrentPaymentsProcessor->chargeRecurrentUsingCid($payment, $recurrentPayment->payment_method->external_token, $gateway);
        if ($success) {
            $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::PAID);
        }

        $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
    }

    private function resolveRedirect($payment, $resolverStatus)
    {
        foreach ($this->paymentCompleteRedirectManager->getResolvers() as $resolver) {
            if ($resolver->wantsToRedirect($payment, $resolverStatus)) {
                $this->redirect(...$resolver->redirectArgs($payment, $resolverStatus));
            }
        }

        throw new \Exception("There's no redirect manager handling this scenario. You should register one or enable remp/crm-sales-funnel-module to enable default handling");
    }

    private function checkPaymentBelongsToUser(User $user, ActiveRow $payment)
    {
        if ($payment->user_id !== $user->getId() || $payment->status !== PaymentStatusEnum::Form->value) {
            $this->redirect('error');
        }
    }
}
