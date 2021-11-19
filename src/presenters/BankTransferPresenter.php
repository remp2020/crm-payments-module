<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Gateways\BankTransfer;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\BadRequestException;
use Nette\Database\Table\IRow;

class BankTransferPresenter extends FrontendPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @persistent  */
    public $id;

    public function renderInfo($id)
    {
        $payment = $this->paymentsRepository->findLastByVS($id);
        if (!$payment) {
            throw new BadRequestException('Payment with variable symbol not found: ' . $id);
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

    public function getPayment(): IRow
    {
        $payment = $this->paymentsRepository->findLastByVS($this->id);
        if (!$payment) {
            throw new BadRequestException('Payment with variable symbol not found: ' . $this->id);
        }

        return $payment;
    }
}
