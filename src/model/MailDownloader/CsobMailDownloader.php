<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\MailParser\CsobMailParser;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;

class CsobMailDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    private $processedFolder;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('confirmation_mail_host');
        $this->imapPort = $config->get('confirmation_mail_port');
        $this->username = $config->get('confirmation_mail_username');
        $this->password = $config->get('confirmation_mail_password');
        $this->processedFolder = $config->get('confirmation_mail_processed_folder');
    }

    public function download($callback)
    {
        $downloader = new Downloader($this->imapHost, $this->imapPort, $this->username, $this->password, $this->processedFolder);

        $criteria = new MailCriteria();
        $criteria->setFrom('notification@csob.cz');
        $criteria->setSubject('CEB Info: Zaúčtování platby');
        $criteria->setUnseen(true);
        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $csobMailParser = new CsobMailParser();
            $mailContent = null;

            // csob changed encoding for some emails and ImapDownloader doesn't provide the header
            // this is a dummy check to verify what encoding was used to encode the content of email
            if (base64_encode(base64_decode($email->getBody())) === $email->getBody()) {
                $mailContent = $csobMailParser->parse(base64_decode($email->getBody()));
            }
            if (quoted_printable_encode(quoted_printable_decode($email->getBody())) === $email->getBody()) {
                $mailContent = $csobMailParser->parse(quoted_printable_decode($email->getBody()));
            }

            if (!$mailContent) {
                return false;
            }

            return $callback($mailContent);
        });
    }
}
