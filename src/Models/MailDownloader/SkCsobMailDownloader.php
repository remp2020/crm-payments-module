<?php

namespace Crm\PaymentsModule\MailConfirmation;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\MailParser\SkCsobMailParser;
use Tomaj\ImapMailDownloader\Downloader;
use Tomaj\ImapMailDownloader\Email;
use Tomaj\ImapMailDownloader\MailCriteria;
use Tracy\Debugger;

class SkCsobMailDownloader
{
    private $imapHost;

    private $imapPort;

    private $username;

    private $password;

    private $processedFolder;

    public function __construct(ApplicationConfig $config)
    {
        $this->imapHost = $config->get('sk_csob_confirmation_host');
        $this->imapPort = $config->get('sk_csob_confirmation_port');
        $this->username = $config->get('sk_csob_confirmation_username');
        $this->password = $config->get('sk_csob_confirmation_password');
        $this->processedFolder = $config->get('sk_csob_confirmation_processed_folder');
    }

    public function download($callback)
    {
        $downloader = new Downloader($this->imapHost, $this->imapPort, $this->username, $this->password, $this->processedFolder);

        $criteria = new MailCriteria();
        $criteria->setFrom('AdminTBS@csob.sk');
        $criteria->setSubject('ČSOB Info 24 - Avízo');
        $criteria->setUnseen(true);
        $downloader->fetch($criteria, function (Email $email) use ($callback) {
            $skCsobMailParser = new SkCsobMailParser();

            // csob changed encoding for some emails and ImapDownloader doesn't provide the header
            // this is a dummy check to verify what encoding was used to encode the content of email
            $mailContent = $skCsobMailParser->parseMulti(base64_decode($email->getBody()));
            if (!empty($mailContent)) {
                return $callback($mailContent);
            }

            $mailContent = $skCsobMailParser->parseMulti(quoted_printable_decode($email->getBody()));
            if (!empty($mailContent)) {
                return $callback($mailContent);
            }

            Debugger::log(
                'Unable to parse CSOB statement (ČSOB Info 24 - Avízo) email from: ' . $email->getDate(),
                Debugger::ERROR
            );
            // email not parsed; do not call callback
            return false;
        });
    }
}
