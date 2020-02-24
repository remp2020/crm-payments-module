<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\BadRequestException;

class BankTransferPresenter extends FrontendPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    public function renderInfo($id)
    {
        $payment = $this->paymentsRepository->findLastByVS($id);
        if (!$payment) {
            throw new BadRequestException('Payment with variable symbol not found: ' . $id);
        }

        $this->template->bankNumber = $this->applicationConfig->get('supplier_bank_account_number');
        $this->template->bankIban = $this->applicationConfig->get('supplier_iban');
        $this->template->bankSwift = $this->applicationConfig->get('supplier_swift');

        $this->template->payment = $payment;
        $this->template->note = 'VS' . $payment->variable_symbol;
    }
}
