<?php

namespace Crm\PaymentsModule\Models\MailConfirmation;

class ImapMailDownloader implements MailDownloaderInterface
{
    public function __construct(private ImapClient $imapClient)
    {
    }

    public function download(array $options, callable $callback): void
    {
        ['port' => $port, 'encryption' => $encryption, 'validateCert' => $validateCert] = $this->parsePort($options['imapPort']);

        /** @var MailCriteria $criteria */
        $criteria = $options['criteria'];

        $this->imapClient->fetch(
            $options['imapHost'],
            $port,
            $encryption,
            $validateCert,
            $options['username'],
            $options['password'],
            $criteria,
            function (ImapMessage $message) use ($callback) {
                $callback(new Email(
                    $message->getBody(),
                    $message->getDate(),
                    array_map(
                        fn($att) => ['attachment' => $att->getContent(), 'name' => $att->getName()],
                        $message->getAttachments(),
                    ),
                ));
            },
            $options['processedFolder'] ?? null,
        );
    }

    private function parsePort(string $imapPort): array
    {
        // Parse the legacy PHP imap_open port format, e.g. "993/imap/ssl" or "993/imap/ssl/novalidate-cert"
        $parts = explode('/', $imapPort);
        $encryption = $parts[2] ?? ($parts[1] !== 'imap' ? $parts[1] : 'ssl');
        $validateCert = !in_array('novalidate-cert', $parts, true);
        return ['port' => (int) $parts[0], 'encryption' => $encryption, 'validateCert' => $validateCert];
    }
}
