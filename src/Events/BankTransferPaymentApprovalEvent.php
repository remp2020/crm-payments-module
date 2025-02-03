<?php

namespace Crm\PaymentsModule\Events;

use League\Event\AbstractEvent;
use Nette\Database\Table\ActiveRow;
use Tomaj\BankMailsParser\MailContent;

class BankTransferPaymentApprovalEvent extends AbstractEvent
{
    private bool $isApproved = true;

    public function __construct(
        public readonly ActiveRow $payment,
        public readonly MailContent $mailContent,
    ) {
    }

    public function disapprove(): void
    {
        $this->isApproved = false;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }
}
