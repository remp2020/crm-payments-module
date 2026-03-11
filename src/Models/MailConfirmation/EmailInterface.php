<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use DateTimeInterface;

interface EmailInterface
{
    public function getBody(): string;

    public function getDate(): DateTimeInterface;

    public function getAttachments(): array;
}
