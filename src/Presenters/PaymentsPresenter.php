<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Utils\DateTime;

class PaymentsPresenter extends FrontendPresenter
{
    private $paymentsRepository;

    private $recurrentPaymentsRepository;

    private $recurrentPaymentsResolver;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        RecurrentPaymentsResolver $recurrentPaymentsResolver
    ) {
        parent::__construct();

        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->recurrentPaymentsResolver = $recurrentPaymentsResolver;
    }

    public function renderMy()
    {
        $this->onlyLoggedIn();

        $this->template->payments = $this->paymentsRepository->userPayments($this->getUser()->getId());
        $this->template->resolver = $this->recurrentPaymentsResolver;
        $this->template->canBeStopped = function ($recurrentPayment) {
            return $this->recurrentPaymentsRepository->canBeStopped($recurrentPayment);
        };
    }

    public function handleReactivate($recurrentId)
    {
        $recurrent = $this->recurrentPaymentsRepository->find($recurrentId);
        if ($this->getUser()->id != $recurrent->user_id) {
            $this->flashMessage($this->translator->translate('payments.frontend.reactivate.error'), 'error');
            $this->redirect('my');
        }
        if (!$recurrent->cid) {
            $this->flashMessage($this->translator->translate('payments.frontend.reactivate.error_create_new'), 'error');
            $this->redirect('my');
        }
        if ($recurrent->charge_at < new DateTime()) {
            $this->flashMessage($this->translator->translate('payments.frontend.reactivate.error_create_new'), 'error');
            $this->redirect('my');
        }
        $this->recurrentPaymentsRepository->reactivateByUser($recurrent->id, $this->getUser()->id);
        $this->flashMessage($this->translator->translate('payments.frontend.reactivate.success'));
        $this->redirect('my');
    }

    public function renderReactivate($recurrentId)
    {
        $this->handleReactivate($recurrentId);
    }

    public function renderRecurrentStop($recurrentPaymentId)
    {
        if (!$recurrentPaymentId) {
            $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.invalid'), 'error');
            $this->redirect('my');
        }
        $this->template->resolver = $this->recurrentPaymentsResolver;
        $this->template->recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
    }

    public function handleStopRecurrentPayment($recurrentPaymentId)
    {
        $this->recurrentPaymentsRepository->stoppedByUser($recurrentPaymentId, $this->getUser()->id);
        $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.success'));
        $this->redirect('My');
    }
}
