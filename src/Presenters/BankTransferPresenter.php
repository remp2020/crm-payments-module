<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Models\Gateways\BankTransfer;
use Crm\PaymentsModule\Models\PaymentAwareInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Application\Attributes\Persistent;
use Nette\Application\BadRequestException;
use Nette\DI\Attributes\Inject;
use Nette\Database\Table\ActiveRow;
use Nette\Http\IResponse;

class BankTransferPresenter extends FrontendPresenter implements PaymentAwareInterface
{
    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Persistent]
    public $id;

    public function renderInfo($id)
    {
        $user = $this->getUser();
        if (!$user->isLoggedIn()) {
            throw new BadRequestException('User is not logged in', httpCode: IResponse::S404_NotFound);
        }

        $payment = $this->paymentsRepository->findLastByVS($id);
        if (!$payment) {
            throw new BadRequestException('Payment with variable symbol not found: ' . $id);
        }

        if ($user->getId() !== $payment->user_id) {
            throw new BadRequestException("User hasn't access to the payment.", httpCode: IResponse::S404_NotFound);
        }

        if ($payment->payment_gateway->code !== BankTransfer::GATEWAY_CODE) {
            throw new BadRequestException('Payment with variable symbol ' . $id . ' has payment gateway ' . $payment->payment_gateway->code . ' instead of ' . BankTransfer::GATEWAY_CODE);
        }

        $this->template->bankNumber = $this->applicationConfig->get('supplier_bank_account_number');
        $this->template->bankIban = $this->applicationConfig->get('supplier_iban');
        $this->template->bankSwift = $this->applicationConfig->get('supplier_swift');

        $this->template->payment = $payment;
        $this->template->note = 'VS' . $payment->variable_symbol;
    }

    public function getPayment(): ActiveRow
    {
        $payment = $this->paymentsRepository->findLastByVS($this->id);
        if (!$payment) {
            throw new BadRequestException('Payment with variable symbol not found: ' . $this->id);
        }

        return $payment;
    }
}
