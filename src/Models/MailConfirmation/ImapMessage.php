<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use DateTimeInterface;

class ImapMessage
{
    /** @param ImapAttachment[] $attachments */
    public function __construct(
        private string $body,
        private DateTimeInterface $date,
        private array $attachments = [],
    ) {
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getDate(): DateTimeInterface
    {
        return $this->date;
    }

    /** @return ImapAttachment[] */
    public function getAttachments(): array
    {
        return $this->attachments;
    }
}
