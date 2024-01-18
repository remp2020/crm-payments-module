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
            $options['processedFolder']
        );

        $downloader->fetch($options['criteria'], function (\Tomaj\ImapMailDownloader\Email $email) use ($callback) {
            $parsedEmail = new Email(
                (string) $email->getBody(),
                DateTime::from($email->getDate()),
                $email->getAttachments(),
            );

            $callback($parsedEmail);
        });
    }
}
