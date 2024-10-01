<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\RecurrentPaymentsResolver;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
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
            return $this->recurrentPaymentsRepository->canBeStoppedByUser($recurrentPayment);
        };
    }

    public function handleReactivate($recurrentId)
    {
        $this->onlyLoggedIn();

        $recurrent = $this->recurrentPaymentsRepository->find($recurrentId);
        if (!$recurrent || $this->getUser()->id != $recurrent->user_id) {
            $this->flashMessage($this->translator->translate('payments.frontend.reactivate.error'), 'error');
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
        $this->onlyLoggedIn();

        if (!$recurrentPaymentId) {
            $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.invalid'), 'error');
            $this->redirect('my');
        }

        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        if (!$recurrentPayment || $this->getUser()->id != $recurrentPayment->user_id) {
            $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.invalid'), 'error');
            $this->redirect('my');
        }

        $this->template->resolver = $this->recurrentPaymentsResolver;
        $this->template->recurrentPayment = $recurrentPayment;
    }

    public function handleStopRecurrentPayment($recurrentPaymentId)
    {
        $this->onlyLoggedIn();

        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentId);
        if (!$recurrentPayment || $this->getUser()->id != $recurrentPayment->user_id) {
            $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.invalid'), 'error');
            $this->redirect('my');
        }

        $this->recurrentPaymentsRepository->stoppedByUser($recurrentPaymentId, $this->getUser()->id);
        $this->flashMessage($this->translator->translate('payments.frontend.recurrent_stop.success'));
        $this->redirect('My');
    }
}
