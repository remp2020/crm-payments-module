<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\AdminModule\Presenters\AdminPresenter;
use Crm\PaymentsModule\Forms\PaymentCountryChangeFormFactory;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShop;
use Crm\PaymentsModule\Models\OneStopShop\OneStopShopCountryConflictException;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Form;
use Nette\Http\IResponse;

class PaymentCountryChangeAdminPresenter extends AdminPresenter
{
    public function __construct(
        private readonly PaymentsRepository $paymentsRepository,
        private readonly OneStopShop $oneStopShop,
        private readonly PaymentCountryChangeFormFactory $paymentCountryChangeFormFactory,
    ) {
        parent::__construct();
    }

    /**
     * @admin-access-level read
     */
    public function renderDefault(int $id): void
    {
        if (!$this->oneStopShop->isEnabled()) {
            $this->flashMessage($this->translator->translate('payments.admin.change_payment_country.disabled_oss_message'));
            $this->redirect('PaymentsAdmin:Default');
        }

        $payment = $this->paymentsRepository->find($id);
        if (!$payment) {
            throw new BadRequestException(sprintf(
                'Payment with id %d not found',
                $id
            ), httpCode: IResponse::S404_NotFound);
        }

        $hasOssConflict = false;
        $countryResolution = null;
        try {
            $countryResolution = $this->paymentCountryChangeFormFactory->getCountryResolution($payment);
        } catch (OneStopShopCountryConflictException) {
            $hasOssConflict = true;
        }

        $this->template->countryResolution = $countryResolution;
        $this->template->hasOssConflict = $hasOssConflict;
        $this->template->payment = $payment;
    }

    public function createComponentPaymentCountryChangeConfirmation(): Form
    {
        $paymentId = $this->getParameter('id');
        $payment = $this->paymentsRepository->find($paymentId);

        $formFactory = $this->paymentCountryChangeFormFactory;
        $formFactory->onConfirmation = function (ActiveRow $payment) {
            $this->flashMessage($this->translator->translate('payments.admin.change_payment_country.success_message'));
            $this->redirect(':Payments:PaymentsAdmin:Show', $payment->id);
        };

        return $formFactory->create($payment);
    }
}
