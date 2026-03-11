<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Nette\Utils\DateTime;
use Tomaj\ImapMailDownloader\Downloader;

class ImapMailDownloader implements MailDownloaderInterface
{
    public function download(array $options, callable $callback): void
    {
        $downloader = new Downloader(
            $options['imapHost'],
            $options['imapPort'],
            $options['username'],
            $options['password'],
            $options['processedFolder'],
        );

        $downloader->fetch($options['criteria'], function (\Tomaj\ImapMailDownloader\Email $email) use ($callback) {
            // handles double timezone specification causing problems (Wed, 11 Mar 2026 18:23:47 +0100 (CET))
            $mailDate = preg_replace('/\s*\([^)]+\)$/', '', $email->getDate());

            $parsedEmail = new Email(
                (string) $email->getBody(),
                DateTime::from($mailDate),
                $email->getAttachments(),
            );

            $callback($parsedEmail);
        });
    }
}
