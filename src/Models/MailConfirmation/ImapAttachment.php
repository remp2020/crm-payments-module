<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

class ImapAttachment
{
    public function __construct(
        private string $content,
        private string $name,
        private string $mimeType,
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }
}
