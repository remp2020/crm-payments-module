<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Nette\Utils\DateTime;

interface EmailInterface
{
    public function getBody(): string;

    public function getDate(): DateTime;

    public function getAttachments(): array;
}
