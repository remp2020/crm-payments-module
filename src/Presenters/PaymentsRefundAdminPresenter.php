<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\PaymentsModule\Forms\PaymentRefundFormFactory;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\UI\Form;

class PaymentsRefundAdminPresenter extends AdminPresenter
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository
    ) {
        parent::__construct();
    }

    public function renderDefault(int $paymentId): void
    {
        $payment = $this->paymentsRepository->find($paymentId);

        $this->template->payment = $payment;
        $this->template->subscription = $payment->subscription;
        $this->template->translator = $this->translator;
    }

    /**
     * @throws DataProviderException
     */
    public function createComponentPaymentRefundForm(PaymentRefundFormFactory $paymentRefundFormFactory): Form
    {
        $form = $paymentRefundFormFactory->create($this->getParameter('paymentId'));

        $paymentRefundFormFactory->onSave = function (int $paymentId) {
            $this->flashMessage(
                $this->translator->translate('payments.admin.payment_refund.form.refund_was_successful')
            );
            $this->redirect(':Payments:PaymentsAdmin:Show', $paymentId);
        };

        return $form;
    }
}
