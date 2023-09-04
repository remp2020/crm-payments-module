<?php

namespace Crm\PaymentsModule\Models\MailDownloader;

use Nette\Utils\DateTime;

class Email implements EmailInterface
{
    public function __construct(
        private string $body,
        private DateTime $dateTime,
        private array $attachments = [],
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDate(): DateTime
    {
        return $this->dateTime;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
