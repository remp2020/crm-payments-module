<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

use Tracy\Debugger;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;
use Webklex\PHPIMAP\Exceptions\MessageFlagException;
use Webklex\PHPIMAP\Exceptions\ResponseException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\IMAP;

class ImapClient
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_SECONDS = 5;

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
        $attempt = 0;
        while (true) {
            $attempt++;
            try {
                $this->doFetch($host, $port, $encryption, $validateCert, $username, $password, $criteria, $callback, $processedFolder);
                return;
            } catch (ConnectionFailedException | GetMessagesFailedException | MessageFlagException | ResponseException | RuntimeException $e) {
                // IMAP servers (Gmail especially) occasionally drop the connection mid-session, which
                // surfaces as "empty response" / connection exceptions. Retrying before throwing an error.
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
                Debugger::log(
                    "IMAP fetch attempt {$attempt}/" . self::MAX_ATTEMPTS . " failed, retrying in " . self::RETRY_DELAY_SECONDS . "s: {$e->getMessage()}",
                    Debugger::WARNING,
                );
                sleep(self::RETRY_DELAY_SECONDS);
            }
        }
    }

    /**
     * @param callable(ImapMessage): void $callback
     */
    private function doFetch(
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

        try {
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
        } finally {
            $client->disconnect();
        }
    }
}
