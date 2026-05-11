<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;

class ImapClient
{
    /**
     * @param callable(ImapMessage): void $callback
     */
    public function fetch(
        string $host,
        int $port,
        string $encryption,
        bool $validateCert,
        string $username,
        string $password,
        MailCriteria $criteria,
        callable $callback,
        ?string $processedFolder = null,
    ): void {
        $cm = new ClientManager(['options' => ['fetch' => IMAP::FT_PEEK]]);
        $client = $cm->make([
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'username' => $username,
            'password' => $password,
            'protocol' => 'imap',
            'validate_cert' => $validateCert,
        ]);
        $client->connect();

        $query = $client->getFolder('INBOX')->query();

        if ($criteria->getFrom() !== null) {
            $query->from($criteria->getFrom());
        }

        // Subject is filtered in PHP below: IMAP quoted strings are 7-bit ASCII only (RFC 3501),
        // so non-ASCII subjects cannot be sent as IMAP search criteria without literal encoding.

        if ($criteria->isUnseen() === true) {
            $query->unseen();
        }

        if ($criteria->getText() !== null) {
            $query->text($criteria->getText());
        }

        foreach ($query->get() as $webklexMessage) {
            if ($criteria->getSubject() !== null) {
                $messageSubject = (string) $webklexMessage->getSubject();
                if (!str_contains($messageSubject, $criteria->getSubject())) {
                    continue;
                }
            }

            $attachments = [];
            foreach ($webklexMessage->getAttachments() as $att) {
                $attachments[] = new ImapAttachment(
                    $att->getContent(),
                    (string) $att->getName(),
                    $att->getMimeType() ?? 'application/octet-stream',
                );
            }

            $message = new ImapMessage(
                (string) ($webklexMessage->getTextBody() ?? $webklexMessage->getHtmlBody() ?? ''),
                $webklexMessage->getDate()->toDate(),
                $attachments,
            );

            $callback($message);

            $webklexMessage->setFlag("Seen");
            if ($processedFolder !== null) {
                $webklexMessage->move($processedFolder);
            }
        }
    }
}
