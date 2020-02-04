<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\MailParser\TatraBankaStatementMailParser;
use Crm\PaymentsModule\MailParser\TatraBankaMailDecryptor;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;
use Tracy\ILogger;

class TatraBankaStatementMailDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    private $processedFolder;

    private $pgpPrivateKeyPath;

    private $pgpPassphrase;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('confirmation_mail_host');
        $this->imapPort = $config->get('confirmation_mail_port');
        $this->username = $config->get('confirmation_mail_username');
        $this->password = $config->get('confirmation_mail_password');
        $this->processedFolder = $config->get('confirmation_mail_processed_folder');

        $this->pgpPrivateKeyPath = $config->get('tatrabanka_pgp_private_key_path');
        $this->pgpPassphrase = $config->get('tatrabanka_pgp_private_key_passphrase');
    }

    public function download($callback)
    {
        $downloader = new Downloader(
            $this->imapHost,
            $this->imapPort,
            $this->username,
            $this->password,
            $this->processedFolder
        );

        $criteria = new MailCriteria();
        $criteria->setFrom('vypis_obchodnik@tatrabanka.sk');
        $criteria->setUnseen(true);
        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $parser = new TatraBankaStatementMailParser(
                new TatraBankaMailDecryptor(
                    $this->pgpPrivateKeyPath,
                    $this->pgpPassphrase
                )
            );
            $mailContent = $parser->parse($email->getBody());

            if (!$mailContent) {
                Debugger::log(
                    'Error in parsing TatraBanka statement email from: ' . $email->getDate(),
                    ILogger::WARNING
                );
                return false;
            }

            return $callback($mailContent);
        });
    }
}
