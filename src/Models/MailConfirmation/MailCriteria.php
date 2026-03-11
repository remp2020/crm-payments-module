<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

class MailCriteria
{
    private ?string $from = null;

    private ?string $subject = null;

    private ?bool $unseen = null;

    private ?string $text = null;

    public function setFrom(string $from): self
    {
        $this->from = $from;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function setUnseen(bool $unseen): self
    {
        $this->unseen = $unseen;
        return $this;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function getFrom(): ?string
    {
        return $this->from;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function isUnseen(): ?bool
    {
        return $this->unseen;
    }

    public function getText(): ?string
    {
        return $this->text;
    }
}
