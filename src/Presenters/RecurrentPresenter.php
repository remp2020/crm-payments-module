<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\CannotProcessPayment;
use Crm\PaymentsModule\GatewayFactory;
use Crm\PaymentsModule\Gateways\RecurrentPaymentInterface;
use Crm\PaymentsModule\Model\PaymentCompleteRedirectManager;
use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\RecurrentPaymentsProcessor;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Security\User;
use Nette\Utils\DateTime;

class RecurrentPresenter extends FrontendPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var RecurrentPaymentsRepository @inject */
    public $recurrentPaymentsRepository;

    /** @var PaymentProcessor @inject */
    public $paymentProcessor;

    /** @var RecurrentPaymentsProcessor @inject */
    public $recurrentPaymentsProcessor;

    /** @var GatewayFactory @inject */
    public $gatewayFactory;

    /** @var PaymentCompleteRedirectManager @inject */
    public $paymentCompleteRedirectManager;

    public function renderSelectCard(int $paymentId)
    {
        $this->onlyLoggedIn();

        $payment = $this->paymentsRepository->find($paymentId);
        if (!$payment) {
            throw new BadRequestException();
        }

        $user = $this->getUser();
        $this->checkPaymentBelongsToUser($user, $payment);

        $allUserCards = $this->recurrentPaymentsRepository
            ->userRecurrentPayments($user->id)
            ->where(['payment_gateway.code = ?' => $payment->ref('payment_gateway')->code])
            ->where(['cid IS NOT NULL AND expires_at > ?' => new DateTime()])
            ->order('id DESC, charge_at DESC')
            ->fetchAll();

        $cardsByExpiration = [];

        foreach ($allUserCards as $card) {
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

        $success = $this->recurrentPaymentsProcessor->chargeRecurrentUsingCid($payment, $recurrentPayment->cid, $gateway);
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
        if ($payment->user_id !== $user->getId() || $payment->status !== PaymentsRepository::STATUS_FORM) {
            $this->redirect('error');
        }
    }
}
