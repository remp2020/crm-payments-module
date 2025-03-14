<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\ApplicationModule\Models\DataProvider\DataProviderException;
use Crm\ApplicationModule\Models\Database\ActiveRow;
use Crm\ApplicationModule\UI\Form;
use Crm\PaymentsModule\Forms\PaymentRefundFormFactory;
use Crm\PaymentsModule\Repositories\PaymentsRepository;

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

        $paymentRefundFormFactory->onSave = function (ActiveRow $payment, array $warnings) {
            if (count($warnings)) {
                $message = sprintf(
                    '%s %s',
                    $this->translator->translate('payments.admin.payment_refund.form.successful_with_warnings'),
                    implode(' ', $warnings),
                );
                $this->flashMessage($message, 'warning');
            } else {
                $this->flashMessage(
                    message: $this->translator->translate('payments.admin.payment_refund.form.refund_was_successful')
                );
            }

            $this->redirect(':Payments:PaymentsAdmin:Show', $payment->id);
        };

        return $form;
    }
}
